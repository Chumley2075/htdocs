"""
depth_helper.py - simple wrapper for SparkFun Qwiic VL53L5CX using
Adafruit FT232H + Blinka.

From recognize.py you only use:

    from depth_helper import DepthHelper
    depth_helper = DepthHelper()
    ok, avg_mm, var_mm = depth_helper.evaluate()

If ok is False, you should skip recognition for that frame.
"""

from __future__ import annotations

import os
from typing import Optional, Tuple

try:
    import vl53lxcx
    import board
    import busio
    from vl53lxcx import (
        DATA_DISTANCE_MM,
        DATA_TARGET_STATUS,
        RESOLUTION_8X8,
        STATUS_VALID,
        VL53L8CX,
    )
    _HAS_TOF_LIB = True
except Exception:
    # library not available – we'll fall back to 2D-only behavior
    vl53lxcx = None  # type: ignore
    _HAS_TOF_LIB = False


def _force_fw_in_package(name: str, length: int) -> str:
    """
    Replacement for vl53lxcx._find_file() that always looks for the
    firmware .bin inside the installed vl53lxcx package directory.
    """
    if vl53lxcx is None:
        raise ValueError("vl53lxcx library not available")

    base = os.path.basename(name)
    pkg_dir = os.path.dirname(vl53lxcx.__file__)  # type: ignore[attr-defined]
    candidate = os.path.join(pkg_dir, base)

    if not os.path.isfile(candidate):
        raise ValueError(f"Firmware file not found in package: {candidate}")

    size = os.path.getsize(candidate)
    if size != length:
        raise ValueError(
            f"Firmware file {candidate} has wrong size {size}, expected {length}"
        )

    return candidate


class DepthHelper:
    """
    Encapsulates VL53L5CX depth logic and exposes a simple evaluate() API.

    evaluate() -> (ok, avg_mm, var_mm)

      - ok: bool – whether this frame passes depth / 3D checks
      - avg_mm: average distance in mm (or None)
      - var_mm: spread (max-min) in mm (or None)

    If the sensor is missing or fails and required=False, ok will be True
    so your app just falls back to 2D-only behavior.
    """

    def __init__(
        self,
        min_depth_mm: int = 300,
        max_depth_mm: int = 2000,
        min_variation_mm: int = 40,
        required: bool = False,
    ) -> None:
        self.min_depth_mm = min_depth_mm
        self.max_depth_mm = max_depth_mm
        self.min_variation_mm = min_variation_mm
        self.required = required

        self._i2c = None
        self._sensor = None
        self._last_depth: Tuple[Optional[float], Optional[float]] = (None, None)
        self._init_attempted = False
        self._init_failed = False

    # ---- internal helpers ----

    def _ensure_sensor(self) -> None:
        """Lazy-init ToF sensor on first use."""
        if self._init_attempted:
            return
        self._init_attempted = True

        if not _HAS_TOF_LIB:
            print("[DepthHelper] vl53lxcx / Blinka not available; ToF disabled", flush=True)
            self._init_failed = True
            return

        try:
            # Monkey-patch firmware lookup so library finds vl53l5cx_fw.bin
            vl53lxcx._find_file = _force_fw_in_package  # type: ignore[attr-defined]

            # I2C on FT232H via Blinka (BLINKA_FT232H=1 must be set in env)
            self._i2c = busio.I2C(board.SCL, board.SDA, frequency=1_000_000)  # type: ignore[name-defined]

            sensor = VL53L8CX(self._i2c)

            # Don't call reset(); LPn is not wired on your Qwiic setup
            if not sensor.is_alive():
                print("[DepthHelper] VL53L5CX not alive on I2C", flush=True)
                self._init_failed = True
                return

            sensor.init()
            sensor.resolution = RESOLUTION_8X8
            sensor.ranging_freq = 5
            sensor.start_ranging({DATA_DISTANCE_MM, DATA_TARGET_STATUS})

            self._sensor = sensor
            print("[DepthHelper] VL53L5CX initialized OK", flush=True)

        except Exception as e:
            print(f"[DepthHelper] init failed: {e}", flush=True)
            self._init_failed = True
            self._sensor = None

    # ---- public API ----

    def evaluate(self) -> Tuple[bool, Optional[float], Optional[float]]:
        """
        Check current depth and decide if frame is OK for face recognition.

        Returns:
            (ok, avg_mm, var_mm)

        Behavior:
          - If sensor missing / failed:
                required=True  -> (False, None, None)
                required=False -> (True,  None, None)  (2D-only fallback)
          - If sensor works:
                ok is True only if:
                    min_depth_mm <= avg_mm <= max_depth_mm
                    and var_mm >= min_variation_mm
        """
        self._ensure_sensor()

        if self._sensor is None:
            # No sensor / init failed
            if self.required:
                return False, None, None
            return True, None, None

        try:
            if not self._sensor.check_data_ready():
                # No fresh frame; reuse last reading
                avg, var = self._last_depth
            else:
                results = self._sensor.get_ranging_data()
                distances = results.distance_mm
                statuses = results.target_status

                valid = [
                    d for i, d in enumerate(distances)
                    if statuses[i] == STATUS_VALID and d > 0
                ]

                if not valid:
                    avg, var = None, None
                else:
                    avg = sum(valid) / len(valid)
                    var = max(valid) - min(valid)

                self._last_depth = (avg, var)

            # Decide
            if avg is None:
                # No depth info; OK unless ToF is strictly required
                if self.required:
                    return False, avg, var
                return True, avg, var

            depth_ok = self.min_depth_mm <= avg <= self.max_depth_mm
            var_ok = (var is None) or (var >= self.min_variation_mm)

            ok = depth_ok and var_ok
            return ok, avg, var

        except Exception as e:
            print(f"[DepthHelper] read failed: {e}", flush=True)
            self._last_depth = (None, None)
            if self.required:
                return False, None, None
            return True, None, None
