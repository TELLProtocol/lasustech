from flask import Flask, request, jsonify
from flask_cors import CORS
import os
from config.attendBlockchain import AttendanceBlockchain
import json
from urllib.parse import urlparse, parse_qs
import time

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
PUBLIC_DIR = os.path.join(BASE_DIR, '../public')

app = Flask(
    __name__,
    static_folder=PUBLIC_DIR,
    static_url_path=''
)
CORS(app)

# Initialize blockchain
ledger = AttendanceBlockchain()

# ──────────────────────────────────────────────────────────────────────────────
# Existing endpoints (from previous refactoring)
# ──────────────────────────────────────────────────────────────────────────────

@app.route('/')
def home():
    return app.send_static_file('index.html')

@app.route('/lecturer')
def lecturer():
    return app.send_static_file('lecturer-dashboard.html')

@app.route('/student')
def student():
    return app.send_static_file('student-dashboard.html')

@app.route('/api/chain', methods=['GET'])
def get_chain():
    """Return the entire blockchain"""
    return jsonify([b.to_dict() for b in ledger.chain])

@app.route('/api/chain/valid', methods=['GET'])
def chain_valid():
    """Check if blockchain is valid"""
    return jsonify({'valid': ledger.is_chain_valid()})

@app.route('/api/contracts', methods=['GET'])
def get_contracts():
    """List all deployed contracts with stats"""
    contracts = []
    for key, contract in ledger.registry._contracts.items():
        sessions = contract.get_sessions()
        attendance = contract.get_all_attendance()
        contracts.append({
            'course_id': contract.course_id,
            'tutor_id': contract.tutor_id,
            'sessions': len(sessions),
            'signins': len(attendance),
            'unique_students': len(set(a['reg_no'] for a in attendance))
        })
    return jsonify(contracts)

@app.route('/api/deploy', methods=['POST'])
def deploy_contract():
    """Deploy a new contract"""
    data = request.json
    course_id = data.get('course_id')
    tutor_id = data.get('tutor_id')
    
    if not course_id or not tutor_id:
        return jsonify({'error': 'Missing course_id or tutor_id'}), 400
    
    try:
        contract = ledger.registry.deploy(course_id, tutor_id)
        return jsonify({'success': True, 'course_id': contract.course_id})
    except ValueError as e:
        return jsonify({'error': str(e)}), 400

@app.route('/api/session/create', methods=['POST'])
def create_session():
    """Create a new attendance session"""
    data = request.json
    course_id = data.get('course_id')
    tutor_id = data.get('tutor_id')
    attendance_id = data.get('attendance_id')
    
    try:
        contract = ledger.registry.get(course_id, tutor_id)
        result = contract.create_session(attendance_id)
        return jsonify(result)
    except Exception as e:
        return jsonify({'error': str(e)}), 400

@app.route('/api/sessions', methods=['GET'])
def get_sessions():
    """Get sessions for a contract"""
    course_id = request.args.get('course_id')
    tutor_id = request.args.get('tutor_id')
    
    try:
        contract = ledger.registry.get(course_id, tutor_id)
        sessions = contract.get_sessions()
        for s in sessions:
            s['signins'] = len(contract.get_session_attendance(s['attendanceID']))
        return jsonify(sessions)
    except Exception as e:
        return jsonify([])

@app.route('/api/sessions/all', methods=['GET'])
def get_all_sessions():
    """Get all session records"""
    sessions = [b.data for b in ledger.chain if b.data.get('type') == 'session_created']
    return jsonify(sessions)

@app.route('/api/session/attendance', methods=['GET'])
def get_session_attendance():
    """Get attendance records for a session"""
    course_id = request.args.get('course_id')
    attendance_id = request.args.get('attendance_id')
    
    try:
        contract = next((c for c in ledger.registry._contracts.values() if c.course_id == course_id), None)
        if not contract:
            return jsonify([])
        records = contract.get_session_attendance(attendance_id)
        return jsonify(records)
    except Exception as e:
        return jsonify([])

@app.route('/api/signins/all', methods=['GET'])
def get_all_signins():
    """Get all attendance records"""
    return jsonify(ledger.get_all_signed())

@app.route('/api/summary', methods=['GET'])
def get_summary():
    """Get attendance summary for a course"""
    course_id = request.args.get('course_id')
    tutor_id = request.args.get('tutor_id')
    
    try:
        contract = ledger.registry.get(course_id, tutor_id)
        return jsonify(contract.attendance_summary())
    except Exception as e:
        return jsonify({'error': str(e)}), 400

# ──────────────────────────────────────────────────────────────────────────────
# NEW STUDENT ENDPOINTS
# ──────────────────────────────────────────────────────────────────────────────

@app.route('/api/student/<reg_no>/attendance', methods=['GET'])
def get_student_attendance(reg_no):
    """Get all attendance for a student across all courses"""
    return jsonify(ledger.get_student_full_record(reg_no))

@app.route('/api/attendance/sign', methods=['POST'])
def sign_attendance():
    """
    Student signs attendance by submitting the scanned QR URL.
    This mirrors the CourseContract.sign_attendance() method.
    """
    data = request.json
    url = data.get('url')
    reg_no = data.get('reg_no')
    
    if not url or not reg_no:
        return jsonify({'error': 'Missing url or reg_no'}), 400
    
    # Parse URL parameters
    try:
        parsed = urlparse(url)
        params = {k: v[0] for k, v in parse_qs(parsed.query).items()}
    except Exception as e:
        return jsonify({'error': f'Invalid URL format: {str(e)}'}), 400
    
    required = {'tutorID', 'course_code', 'validity'}
    missing = required - params.keys()
    if missing:
        return jsonify({'error': f'Missing URL parameters: {missing}'}), 400
    
    tutor_id = params['tutorID']
    course_code = params['course_code']
    validity = float(params['validity'])
    now = time.time()
    
    # Check QR validity window (30 minutes)
    QR_VALIDITY_WINDOW = 30 * 60
    if now - validity > QR_VALIDITY_WINDOW:
        return jsonify({'error': f'QR code expired ({QR_VALIDITY_WINDOW // 60} min limit). Ask your lecturer to regenerate.'}), 400
    
    # Get the contract and sign attendance
    try:
        contract = ledger.registry.get(course_code, tutor_id)
        
        # Check if student already signed for any active session of this course
        active_sessions = contract.get_sessions()
        for session in active_sessions:
            if contract._already_signed(session['attendanceID'], reg_no):
                return jsonify({'error': f"'{reg_no}' has already signed attendance for {course_code}."}), 400
        
        # Find the most recent active session
        now = time.time()
        active_sessions = [
            s for s in active_sessions
            if now - s.get('created_at', 0) <= QR_VALIDITY_WINDOW
        ]
        if not active_sessions:
            return jsonify({'error': 'No active session found for this course.'}), 400
        
        session = sorted(active_sessions, key=lambda s: s.get('created_at', 0))[-1]
        attendance_id = session['attendanceID']
        
        # Record attendance
        record = {
            "type": "attendance_signed",
            "tutorID": tutor_id,
            "course_id": course_code,
            "attendanceID": attendance_id,
            "reg_no": reg_no.upper().strip(),
            "signed_at": now,
        }
        block = ledger._append(record)
        
        return jsonify({
            'success': True,
            'message': f'Attendance recorded for {reg_no} on {course_code}.',
            'block_index': block.index,
            'block_hash': block.hash,
            'record': record
        })
    except KeyError:
        return jsonify({'error': f'No contract found for {course_code} / {tutor_id}. Please check the QR code.'}), 400
    except Exception as e:
        return jsonify({'error': str(e)}), 400

if __name__ == '__main__':
    port = int(os.environ.get("PORT", 8080))
    app.run(host='0.0.0.0', port=port)
