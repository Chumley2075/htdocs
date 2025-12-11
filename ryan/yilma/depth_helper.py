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
    vl53lxcx = None  # type: ignore
    _HAS_TOF_LIB = False


def _force_fw_in_package(name: str, length: int) -> str:
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
        self._last_distances = None
        self._last_statuses = None
        self._init_attempted = False
        self._init_failed = False

    def _ensure_sensor(self) -> None:
        if self._init_attempted:
            return
        self._init_attempted = True
        if not _HAS_TOF_LIB:
            print("[DepthHelper] vl53lxcx / Blinka not available; ToF disabled", flush=True)
            self._init_failed = True
            return
        try:
            vl53lxcx._find_file = _force_fw_in_package  # type: ignore[attr-defined]
            self._i2c = busio.I2C(board.SCL, board.SDA, frequency=1_000_000)  # type: ignore[name-defined]
            sensor = VL53L8CX(self._i2c)
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

    def evaluate(self) -> Tuple[bool, Optional[float], Optional[float]]:
        self._ensure_sensor()
        if self._sensor is None:
            if self.required:
                return False, None, None
            return True, None, None
        try:
            if not self._sensor.check_data_ready():
                avg, var = self._last_depth
            else:
                results = self._sensor.get_ranging_data()
                distances = results.distance_mm
                statuses = results.target_status
                self._last_distances = distances
                self._last_statuses = statuses
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
            if avg is None:
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

    def evaluate_for_box(
        self,
        x: int,
        y: int,
        w: int,
        h: int,
        frame_width: int,
        frame_height: int,
    ) -> Tuple[bool, Optional[float], Optional[float]]:
        ok_global, avg_global, var_global = self.evaluate()
        if (
            self._sensor is None or
            self._last_distances is None or
            self._last_statuses is None
        ):
            return ok_global, avg_global, var_global
        distances = self._last_distances
        statuses = self._last_statuses
        cx = x + w / 2.0
        cy = y + h / 2.0
        if frame_width <= 0 or frame_height <= 0:
            return ok_global, avg_global, var_global
        gx = int(cx / frame_width * 8)
        gy = int(cy / frame_height * 8)
        gx = max(0, min(7, gx))
        gy = max(0, min(7, gy))
        idx = gy * 8 + gx
        local_vals = []
        if statuses[idx] == STATUS_VALID and distances[idx] > 0:
            local_vals.append(distances[idx])

        if not local_vals:
            return ok_global, avg_global, var_global
        avg = sum(local_vals) / len(local_vals)
        var = max(local_vals) - min(local_vals)
        depth_ok = self.min_depth_mm <= avg <= self.max_depth_mm
        var_ok = (var is None) or (var >= self.min_variation_mm)
        ok = depth_ok and var_ok
        return ok, avg, var
