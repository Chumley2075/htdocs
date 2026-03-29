from __future__ import annotations

import os
import time
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
        min_face_valid_samples: int = 3,
        required: bool = False,
    ) -> None:
        self.min_depth_mm = min_depth_mm
        self.max_depth_mm = max_depth_mm
        self.min_variation_mm = min_variation_mm
        self.min_face_valid_samples = max(1, min_face_valid_samples)
        self.required = required
        self._i2c = None
        self._sensor = None
        self._last_depth: Tuple[Optional[float], Optional[float]] = (None, None)
        self._last_distances = None
        self._last_statuses = None
        self._last_data_at: Optional[float] = None
        self._last_eval_details = {
            "mode": "uninitialized",
            "source": "none",
            "reason": "not_evaluated",
            "fresh": False,
            "valid_samples": 0,
            "avg_depth_mm": None,
            "variation_mm": None,
        }
        self._init_attempted = False
        self._init_failed = False

    def _store_eval_details(
        self,
        *,
        mode: str,
        source: str,
        reason: str,
        fresh: bool,
        valid_samples: int,
        avg_depth_mm: Optional[float],
        variation_mm: Optional[float],
        face_cells: Optional[int] = None,
    ) -> None:
        self._last_eval_details = {
            "mode": mode,
            "source": source,
            "reason": reason,
            "fresh": fresh,
            "valid_samples": valid_samples,
            "avg_depth_mm": avg_depth_mm,
            "variation_mm": variation_mm,
            "face_cells": face_cells,
            "sensor_ready": self._sensor is not None,
            "last_data_age_s": None if self._last_data_at is None else max(0.0, time.monotonic() - self._last_data_at),
        }

    def get_last_eval_details(self):
        return dict(self._last_eval_details)

    def _decide_reason(
        self,
        avg: Optional[float],
        var: Optional[float],
        *,
        depth_ok: bool,
        var_ok: bool,
        valid_samples: int,
    ) -> str:
        if valid_samples <= 0:
            return "no_valid_samples"
        if avg is None:
            return "no_depth_value"
        if not depth_ok:
            return "depth_out_of_range"
        if not var_ok:
            return "variation_too_low"
        return "ok"

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
                self._store_eval_details(
                    mode="global",
                    source="sensor_unavailable",
                    reason="sensor_required_but_unavailable",
                    fresh=False,
                    valid_samples=0,
                    avg_depth_mm=None,
                    variation_mm=None,
                )
                return False, None, None
            self._store_eval_details(
                mode="global",
                source="sensor_unavailable",
                reason="sensor_optional_bypass",
                fresh=False,
                valid_samples=0,
                avg_depth_mm=None,
                variation_mm=None,
            )
            return True, None, None
        try:
            fresh = False
            valid_samples = 0
            if not self._sensor.check_data_ready():
                avg, var = self._last_depth
            else:
                results = self._sensor.get_ranging_data()
                distances = results.distance_mm
                statuses = results.target_status
                self._last_distances = distances
                self._last_statuses = statuses
                self._last_data_at = time.monotonic()
                fresh = True
                valid = [
                    d for i, d in enumerate(distances)
                    if statuses[i] == STATUS_VALID and d > 0
                ]
                valid_samples = len(valid)
                if not valid:
                    avg, var = None, None
                else:
                    avg = sum(valid) / len(valid)
                    var = max(valid) - min(valid)
                self._last_depth = (avg, var)
            if not fresh and self._last_distances is not None and self._last_statuses is not None:
                valid_samples = sum(
                    1 for i, d in enumerate(self._last_distances)
                    if self._last_statuses[i] == STATUS_VALID and d > 0
                )
            if avg is None:
                if self.required:
                    self._store_eval_details(
                        mode="global",
                        source="global_grid",
                        reason="no_valid_samples",
                        fresh=fresh,
                        valid_samples=valid_samples,
                        avg_depth_mm=avg,
                        variation_mm=var,
                    )
                    return False, avg, var
                self._store_eval_details(
                    mode="global",
                    source="global_grid",
                    reason="no_valid_samples_optional_bypass",
                    fresh=fresh,
                    valid_samples=valid_samples,
                    avg_depth_mm=avg,
                    variation_mm=var,
                )
                return True, avg, var
            depth_ok = self.min_depth_mm <= avg <= self.max_depth_mm
            var_ok = (var is None) or (var >= self.min_variation_mm)
            reason = self._decide_reason(
                avg,
                var,
                depth_ok=depth_ok,
                var_ok=var_ok,
                valid_samples=valid_samples,
            )
            ok = depth_ok and var_ok
            self._store_eval_details(
                mode="global",
                source="global_grid",
                reason=reason,
                fresh=fresh,
                valid_samples=valid_samples,
                avg_depth_mm=avg,
                variation_mm=var,
            )
            return ok, avg, var
        except Exception as e:
            print(f"[DepthHelper] read failed: {e}", flush=True)
            self._last_depth = (None, None)
            if self.required:
                self._store_eval_details(
                    mode="global",
                    source="sensor_error",
                    reason="read_failed_required",
                    fresh=False,
                    valid_samples=0,
                    avg_depth_mm=None,
                    variation_mm=None,
                )
                return False, None, None
            self._store_eval_details(
                mode="global",
                source="sensor_error",
                reason="read_failed_optional_bypass",
                fresh=False,
                valid_samples=0,
                avg_depth_mm=None,
                variation_mm=None,
            )
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
        if frame_width <= 0 or frame_height <= 0:
            return ok_global, avg_global, var_global

        x1 = max(0.0, min(float(frame_width), float(x)))
        y1 = max(0.0, min(float(frame_height), float(y)))
        x2 = max(x1, min(float(frame_width), float(x + w)))
        y2 = max(y1, min(float(frame_height), float(y + h)))

        gx1 = max(0, min(7, int(x1 / frame_width * 8)))
        gy1 = max(0, min(7, int(y1 / frame_height * 8)))
        gx2 = max(0, min(7, int(((x2 if x2 < frame_width else frame_width - 1) / frame_width) * 8)))
        gy2 = max(0, min(7, int(((y2 if y2 < frame_height else frame_height - 1) / frame_height) * 8)))

        local_vals = []
        face_cells = 0
        for gy in range(min(gy1, gy2), max(gy1, gy2) + 1):
            for gx in range(min(gx1, gx2), max(gx1, gx2) + 1):
                face_cells += 1
                idx = gy * 8 + gx
                if statuses[idx] == STATUS_VALID and distances[idx] > 0:
                    local_vals.append(distances[idx])

        if not local_vals:
            self._store_eval_details(
                mode="face_region",
                source="global_fallback",
                reason="no_valid_face_cells",
                fresh=self.get_last_eval_details().get("fresh", False),
                valid_samples=0,
                avg_depth_mm=avg_global,
                variation_mm=var_global,
                face_cells=face_cells,
            )
            return ok_global, avg_global, var_global

        if len(local_vals) < self.min_face_valid_samples:
            self._store_eval_details(
                mode="face_region",
                source="global_fallback",
                reason="insufficient_face_samples",
                fresh=self.get_last_eval_details().get("fresh", False),
                valid_samples=len(local_vals),
                avg_depth_mm=avg_global,
                variation_mm=var_global,
                face_cells=face_cells,
            )
            return ok_global, avg_global, var_global

        avg = sum(local_vals) / len(local_vals)
        var = max(local_vals) - min(local_vals)
        depth_ok = self.min_depth_mm <= avg <= self.max_depth_mm
        var_ok = (var is None) or (var >= self.min_variation_mm)
        reason = self._decide_reason(
            avg,
            var,
            depth_ok=depth_ok,
            var_ok=var_ok,
            valid_samples=len(local_vals),
        )
        ok = depth_ok and var_ok
        self._store_eval_details(
            mode="face_region",
            source="face_region_grid",
            reason=reason,
            fresh=self.get_last_eval_details().get("fresh", False),
            valid_samples=len(local_vals),
            avg_depth_mm=avg,
            variation_mm=var,
            face_cells=face_cells,
        )
        return ok, avg, var
