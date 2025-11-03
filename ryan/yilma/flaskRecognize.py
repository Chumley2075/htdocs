from flask import Flask, Response, render_template_string, make_response
from recognize import generate_frames, stop_camera

app = Flask(__name__)

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
    resp = Response(generate_frames(),
                    mimetype='multipart/x-mixed-replace; boundary=frame')
    resp.headers['Cache-Control'] = 'no-store'
    return resp
@app.route('/stop_feed')
def stop_feed():
    stop_camera()
    return ("Camera stopped", 200)

@app.route('/reload_trainer')
def reload_trainer():
    from recognize import load_trainer_from_db, stop_camera
    stop_camera()
    load_trainer_from_db()
    return ("Trainer reloaded", 200)

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
    resp.headers['Cache-Control'] = 'no-store'
    resp.headers['Access-Control-Allow-Origin'] = '*'
    return resp

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5001, debug=False)
