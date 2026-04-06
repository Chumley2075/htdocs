import glob
import os
import time

import cv2

CAMERA_RELEASE_COOLDOWN_S = 0.2
CAMERA_OPEN_RETRY_TIMEOUT_S = 4.0
CAMERA_OPEN_RETRY_INTERVAL_S = 0.2


class CameraOpenError(RuntimeError):
    pass


def _video_device_path(index):
    return f"/dev/video{index}"


def _available_video_devices():
    return sorted(glob.glob("/dev/video*"))


def _build_open_error(index):
    device_path = _video_device_path(index)
    devices = _available_video_devices()
    if not os.path.exists(device_path):
        return (
            f"Camera index {index} is unavailable; {device_path} does not exist. "
            f"Available video devices: {', '.join(devices) if devices else 'none'}"
        )
    return (
        f"Camera index {index} exists at {device_path} but could not be opened. "
        "The camera may be busy, blocked by permissions, or disconnected. "
        f"Available video devices: {', '.join(devices) if devices else device_path}"
    )


def _device_index_from_path(path):
    base = os.path.basename(path)
    if not base.startswith("video"):
        return None
    suffix = base[5:]
    if not suffix.isdigit():
        return None
    return int(suffix)


def _candidate_indexes(preferred_index: int) -> list[int]:
    candidates = [preferred_index]
    for path in _available_video_devices():
        idx = _device_index_from_path(path)
        if idx is None or idx in candidates:
            continue
        candidates.append(idx)
    return candidates


def release_camera(cam, cooldown_s=CAMERA_RELEASE_COOLDOWN_S):
    try:
        if cam is not None and cam.isOpened():
            cam.release()
    except Exception:
        pass
    if cooldown_s > 0:
        time.sleep(cooldown_s)


def _try_open_once(
    index=0,
    width=None,
    height=None,
    fps=None,
    warmup_frames=12,
    warmup_timeout_s=1.5,
):
    cam = cv2.VideoCapture(index)
    if not cam.isOpened():
        release_camera(cam, cooldown_s=0)
        raise CameraOpenError(_build_open_error(index))
    if width is not None:
        cam.set(cv2.CAP_PROP_FRAME_WIDTH, width)
    if height is not None:
        cam.set(cv2.CAP_PROP_FRAME_HEIGHT, height)
    if fps is not None:
        cam.set(cv2.CAP_PROP_FPS, fps)
    warmed = warm_camera(cam, warmup_frames=warmup_frames, warmup_timeout_s=warmup_timeout_s)
    if not warmed:
        device_path = _video_device_path(index)
        release_camera(cam)
        raise CameraOpenError(
            f"Camera index {index} opened at {device_path} but did not produce frames during warmup. "
            "The device may be busy, blocked, or not streaming correctly."
        )
    return cam


def open_camera(
    index=0,
    width=None,
    height=None,
    fps=None,
    warmup_frames=12,
    warmup_timeout_s=1.5,
    retry_timeout_s=CAMERA_OPEN_RETRY_TIMEOUT_S,
    retry_interval_s=CAMERA_OPEN_RETRY_INTERVAL_S,
):
    errors = []
    retry_deadline = time.time() + max(0.0, retry_timeout_s)

    while True:
        try:
            return _try_open_once(
                index=index,
                width=width,
                height=height,
                fps=fps,
                warmup_frames=warmup_frames,
                warmup_timeout_s=warmup_timeout_s,
            )
        except CameraOpenError as exc:
            errors = [str(exc)]
            if time.time() >= retry_deadline:
                break
            time.sleep(max(0.05, retry_interval_s))

    for candidate_index in _candidate_indexes(index):
        if candidate_index == index:
            continue
        try:
            cam = _try_open_once(
                index=candidate_index,
                width=width,
                height=height,
                fps=fps,
                warmup_frames=warmup_frames,
                warmup_timeout_s=warmup_timeout_s,
            )
            print(
                f"[INFO] Falling back from camera index {index} to working camera index {candidate_index}",
                flush=True,
            )
            return cam
        except CameraOpenError as exc:
            errors.append(str(exc))
    raise CameraOpenError(" | ".join(errors) if errors else _build_open_error(index))


def warm_camera(cam, warmup_frames=12, warmup_timeout_s=1.5):
    deadline = time.time() + max(0.1, warmup_timeout_s)
    warmed = 0
    while warmed < max(1, warmup_frames) and time.time() < deadline:
        ok, frame = cam.read()
        if not ok or frame is None or frame.size == 0:
            time.sleep(0.05)
            continue
        warmed += 1
    return warmed > 0


def prewarm_camera(
    index=0,
    width=None,
    height=None,
    fps=None,
    warmup_frames=12,
    warmup_timeout_s=1.5,
):
    cam = None
    try:
        cam = open_camera(
            index=index,
            width=width,
            height=height,
            fps=fps,
            warmup_frames=warmup_frames,
            warmup_timeout_s=warmup_timeout_s,
        )
        return True
    except Exception as exc:
        print(f"[WARN] Camera prewarm failed: {exc}")
        return False
    finally:
        release_camera(cam)
