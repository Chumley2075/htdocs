from flask import Flask, Response, render_template_string, make_response, jsonify, request
from recognize import generate_frames, stop_camera, prepare_scan

app = Flask(__name__)


@app.after_request
def add_headers(resp):
    resp.headers['Access-Control-Allow-Origin'] = '*'
    resp.headers['Access-Control-Allow-Headers'] = 'Content-Type'
    resp.headers['Access-Control-Allow-Methods'] = 'GET, POST, OPTIONS'
    resp.headers['Cache-Control'] = 'no-store'
    return resp

@app.route('/')
def index():
    return render_template_string("""
        <html>
        <head><title>Recognize Capture Stream</title></head>
        <body style="background:#111; color:#eee; text-align:center;">
        <h2>Live Face Capture</h2>
        <img src="{{ url_for('video_feed') }}" width="640" height="480" 
             style="border-radius:12px; margin-top:20px;" />
        </body>
        </html>
    """)

@app.route('/video_feed')
def video_feed():
    door_id = request.args.get('door_id')
    resp = Response(generate_frames(door_id=door_id),
                    mimetype='multipart/x-mixed-replace; boundary=frame')
    return resp
@app.route('/stop_feed')
def stop_feed():
    stop_camera()
    return ("Camera stopped", 200)

@app.route('/reload_trainer')
def reload_trainer():
    from recognize import load_trainer_from_db, stop_camera
    stop_camera()
    load_trainer_from_db(force=True)
    return ("Trainer reloaded", 200)


@app.route('/prepare_scan')
def prepare_scan_route():
    force_reload = request.args.get('reload') == '1'
    warmed = prepare_scan(force_reload=force_reload)
    resp = jsonify({"ok": bool(warmed)})
    return resp

@app.route('/label')
def label():
    try:
        from recognize import get_latest_labels
        txt = get_latest_labels()
    except Exception:
        try:
            with open("/tmp/last_label.txt", "r") as f:
                txt = f.read().strip()
        except Exception:
            txt = "Unknown"
    resp = make_response(txt if txt else "Unknown")
    resp.headers['Content-Type'] = 'text/plain; charset=utf-8'
    return resp


@app.route('/scan_result')
def scan_result():
    try:
        from recognize import get_latest_scan_result
        result = get_latest_scan_result()
    except Exception:
        result = {
            "status": "idle",
            "label": "Unknown",
            "image_url": "",
            "token": "",
            "message": "Scan status unavailable.",
            "timestamp": 0,
        }
    return jsonify(result)


@app.route('/opt_out_face', methods=['POST', 'OPTIONS'])
def opt_out_face():
    if request.method == 'OPTIONS':
        return ('', 204)
    data = request.get_json(silent=True) or request.form
    label_value = data.get('label', '')
    token = data.get('token', '')
    try:
        from recognize import opt_out_latest_face
        ok, message = opt_out_latest_face(label_value, token)
    except Exception as exc:
        ok = False
        message = f"Opt-out failed: {exc}"
    status = 200 if ok else 400
    return jsonify({"ok": bool(ok), "message": message}), status


@app.route('/door_state')
def door_state():
    door_id = request.args.get('door_id')
    try:
        from recognize import get_door_state
        state = get_door_state(door_id=door_id)
    except Exception:
        state = {
            "door_id": door_id,
            "room_number": None,
            "is_locked": 0,
            "lock_mode": "unlocked",
            "lock_reason": "",
            "last_changed_by": "",
            "last_changed_at": None,
        }
    resp = jsonify(state)
    return resp

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5001, debug=False)
