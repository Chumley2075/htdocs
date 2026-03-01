from flask import Flask, Response, request, render_template_string
from captureFaces import generate_frames  


app = Flask(__name__)

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
    return Response(generate_frames(person_id, full_name),
                    mimetype='multipart/x-mixed-replace; boundary=frame')
if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=False)
