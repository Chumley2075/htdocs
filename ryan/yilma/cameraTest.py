from flask import Flask, Response, request, render_template_string, jsonify
from captureFaces import generate_frames, stop_capture, warmup_camera, get_capture_status


app = Flask(__name__)


@app.after_request
def add_cors_headers(resp):
    resp.headers['Access-Control-Allow-Origin'] = '*'
    resp.headers['Cache-Control'] = 'no-store'
    return resp

@app.route('/')
def index():
    return render_template_string("""
        <html>
        <head><title>Face Capture Stream</title></head>
        <body style="background:#111; color:#eee; text-align:center;">
        <h2>Live Face Capture</h2>
        <img src="{{ url_for('video_feed') }}" width="640" height="480" 
             style="border-radius:12px; margin-top:20px;" />
        </body>
        </html>
    """)

@app.route('/video_feed')
def video_feed():
    person_id = request.args.get('person_id', 'unknown')
    full_name = request.args.get('full_name', '')
    return Response(
        generate_frames(person_id, full_name),
        mimetype='multipart/x-mixed-replace; boundary=frame'
    )


@app.route('/warmup')
def warmup():
    return jsonify({"ok": bool(warmup_camera())})


@app.route('/capture_status')
def capture_status():
    person_id = request.args.get('person_id')
    return jsonify(get_capture_status(person_id))


@app.route('/stop_feed')
def stop_feed():
    stop_capture()
    return jsonify({"ok": True, "message": "Camera stopped"})


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=False)
