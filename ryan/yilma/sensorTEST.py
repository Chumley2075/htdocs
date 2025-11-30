import os
import time
import board
import busio
import vl53lxcx
from vl53lxcx import (
    DATA_DISTANCE_MM,
    DATA_TARGET_STATUS,
    RESOLUTION_8X8,
    STATUS_VALID,
    VL53L8CX,
)
def _force_fw_in_package(name: str, length: int) -> str:
    """
    Replacement for vl53lxcx._find_file() that always looks for the
    firmware .bin inside the installed vl53lxcx package directory.

    We ignore the directory part of 'name' and use just the basename,
    because the package already ships with:
        vl53l5cx_fw.bin
        vl53l8cx_fw.bin
    """
    base = os.path.basename(name)
    pkg_dir = os.path.dirname(vl53lxcx.__file__)
    candidate = os.path.join(pkg_dir, base)

    if not os.path.isfile(candidate):
        raise ValueError(f"Firmware file not found in package: {candidate}")

    size = os.path.getsize(candidate)
    if size != length:
        raise ValueError(
            f"Firmware file {candidate} has wrong size {size}, "
            f"expected {length}"
        )

    return candidate

# Replace the helper used inside the driver
vl53lxcx._find_file = _force_fw_in_package

i2c = busio.I2C(board.SCL, board.SDA, frequency=1_000_000)

tof = VL53L8CX(i2c)

#tof.reset()
if not tof.is_alive():
    raise RuntimeError("VL53L5CX not detected over I2C")

tof.init()
tof.resolution = RESOLUTION_8X8
tof.ranging_freq = 5  # Hz

tof.start_ranging({DATA_DISTANCE_MM})

while True:
    if tof.check_data_ready():
        results = tof.get_ranging_data()
        distances = results.distance_mm  # list of 64 zones (8x8)

        # Print a simple “average distance”
        valid = [d for d in distances if d > 0]
        if valid:
            avg = sum(valid) / len(valid)
            print(f"Average distance: {avg:.1f} mm")
        else:
            print("No valid distance samples")

        time.sleep(0.1)
