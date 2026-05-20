from flask import Flask, request, jsonify
from flask_cors import CORS
from attendBlockchain import AttendanceBlockchain
import json

app = Flask(__name__)
CORS(app)

# Initialize blockchain
ledger = AttendanceBlockchain()

@app.route('/api/chain', methods=['GET'])
def get_chain():
    """Return the entire blockchain"""
    return jsonify([b.to_dict() for b in ledger.chain])

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

@app.route('/api/sessions/all', methods=['GET'])
def get_all_sessions():
    """Get all session records"""
    sessions = [b.data for b in ledger.chain if b.data.get('type') == 'session_created']
    return jsonify(sessions)

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

@app.route('/api/student/<reg_no>/attendance', methods=['GET'])
def get_student_attendance(reg_no):
    """Get all attendance for a student"""
    return jsonify(ledger.get_student_full_record(reg_no))

@app.route('/api/chain/valid', methods=['GET'])
def chain_valid():
    """Check if blockchain is valid"""
    return jsonify({'valid': ledger.is_chain_valid()})

if __name__ == '__main__':
    app.run(debug=True, port=5000)