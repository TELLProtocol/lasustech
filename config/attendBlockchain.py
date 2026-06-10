import hashlib
import hmac
import json
import os
import secrets
from time import time
from urllib.parse import urlparse, parse_qs

# ── Configuration ─────────────────────────────────────────────────────────────
QR_VALIDITY_WINDOW = 12 * 60      # QR expires after 12 minutes
STORAGE_BACKEND    = "mysql"       # "json" or "mysql"
LEDGER_FILE        = "/tmp/ledger.json"

MYSQL_CONFIG = {
    "host":     os.environ.get("DB_HOST", "4kdqoi.h.filess.io"),
    "port":     int(os.environ.get("DB_PORT", "61002")),
    "user":     os.environ.get("DB_USER", "lasustech_lastbeltbe"),
    "password": os.environ.get("DB_PASSWORD", "lasustech"),
    "database": os.environ.get("DB_NAME", "lasustech_lastbeltbe"),
}


# ══════════════════════════════════════════════════════════════════════════════
# Block
# ══════════════════════════════════════════════════════════════════════════════
class Block:
    def __init__(self, index, data, previous_hash, timestamp=None):
        self.index         = index
        self.timestamp     = timestamp if timestamp is not None else str(time())
        self.data          = data
        self.previous_hash = previous_hash
        self.hash          = self.calculate_hash()

    def calculate_hash(self):
        block_string = json.dumps({
            "index":         self.index,
            "timestamp":     self.timestamp,
            "data":          self.data,
            "previous_hash": self.previous_hash,
        }, sort_keys=True).encode()
        return hashlib.sha256(block_string).hexdigest()

    def to_dict(self):
        return {
            "index":         self.index,
            "timestamp":     self.timestamp,
            "data":          self.data,
            "previous_hash": self.previous_hash,
            "hash":          self.hash,
        }

    @classmethod
    def from_dict(cls, d):
        block      = cls(d["index"], d["data"], d["previous_hash"], d["timestamp"])
        block.hash = d["hash"]
        return block


# ══════════════════════════════════════════════════════════════════════════════
# Storage backends
# ══════════════════════════════════════════════════════════════════════════════
class JSONStorage:
    def __init__(self, filepath=LEDGER_FILE):
        self.filepath = filepath

    def load(self) -> list:
        if not os.path.exists(self.filepath):
            return []
        with open(self.filepath, "r") as f:
            return [Block.from_dict(d) for d in json.load(f)]

    def save(self, chain: list):
        with open(self.filepath, "w") as f:
            json.dump([b.to_dict() for b in chain], f, indent=2)


class MySQLStorage:
    """
    Required table (run once):

        CREATE TABLE blockchain (
            idx           INT PRIMARY KEY,
            timestamp     VARCHAR(40) NOT NULL,
            data          JSON   NOT NULL,
            previous_hash VARCHAR(64) NOT NULL,
            hash          VARCHAR(64) NOT NULL
        );

    Install: pip install PyMySQL cryptography
    """
    def __init__(self, config=None):
        self.config = config or MYSQL_CONFIG
        try:
            import pymysql
            import pymysql.cursors
            self._pymysql    = pymysql
            self._DictCursor = pymysql.cursors.DictCursor
        except ImportError:
            raise ImportError("Run: pip install PyMySQL cryptography")

    def _connect(self):
        return self._pymysql.connect(
            host=self.config["host"],
            port=self.config["port"],
            user=self.config["user"],
            password=self.config["password"],
            database=self.config["database"],
            cursorclass=self._DictCursor
        )

    def load(self) -> list:
        conn   = self._connect()
        cursor = conn.cursor()
        cursor.execute("SELECT * FROM blockchain ORDER BY idx ASC")
        rows   = cursor.fetchall()
        cursor.close()
        conn.close()
        blocks = []
        for row in rows:
            d = {
                "index":         row["idx"],
                "timestamp":     row["timestamp"],
                "data":          json.loads(row["data"]) if isinstance(row["data"], str) else row["data"],
                "previous_hash": row["previous_hash"],
                "hash":          row["hash"],
            }
            blocks.append(Block.from_dict(d))
        return blocks

    def save(self, chain: list):
        conn   = self._connect()
        cursor = conn.cursor()
        sql = """
            INSERT INTO blockchain (idx, timestamp, data, previous_hash, hash)
            VALUES (%s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE
                timestamp=VALUES(timestamp), data=VALUES(data),
                previous_hash=VALUES(previous_hash), hash=VALUES(hash)
        """
        for b in chain:
            cursor.execute(sql, (b.index, str(b.timestamp), json.dumps(b.data), b.previous_hash, b.hash))
        conn.commit()
        cursor.close()
        conn.close()


def get_storage():
    return MySQLStorage() if STORAGE_BACKEND == "mysql" else JSONStorage()


# ══════════════════════════════════════════════════════════════════════════════
# UserStore  ←  persists lecturer / student accounts in MySQL
# ══════════════════════════════════════════════════════════════════════════════
class UserStore:
    """
    Manages user accounts in a dedicated MySQL table.

    Required table (run once):

        CREATE TABLE users (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            email         VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(128) NOT NULL,   -- bcrypt hash (60 chars)
            role          ENUM('lecturer','student') NOT NULL,
            identifier    VARCHAR(100) NOT NULL,   -- tutor_id or reg_no
            created_at    DOUBLE NOT NULL
        );
        
    Install: pip install PyMySQL cryptography bcrypt
    """
    
    BCRYPT_ROUNDS = 12  # work factor — increase on faster hardware

    def __init__(self, config=None):
        self.config = config or MYSQL_CONFIG
        try:
            import pymysql
            import pymysql.cursors
            self._pymysql    = pymysql
            self._DictCursor = pymysql.cursors.DictCursor
        except ImportError:
            raise ImportError("Run: pip install PyMySQL cryptography")
        try:
            import bcrypt
            self._bcrypt = bcrypt
        except ImportError:
            raise ImportError("Run: pip install bcrypt")
        

    def _connect(self):
        return self._pymysql.connect(
            host=self.config["host"],
            port=self.config["port"],
            user=self.config["user"],
            password=self.config["password"],
            database=self.config["database"],
            cursorclass=self._DictCursor,
        )

    def register(self, email: str, password: str, role: str,
                 identifier: str = "") -> dict:
        """
        Create a new user account.

        Parameters
        ----------
        email       : unique login email
        password    : plain-text password (hashed before storage)
        role        : 'lecturer' or 'student'
        identifier  : tutor_id for lecturers, reg_no for students

        Returns {'success': True, 'user': {...}} or {'success': False, 'error': '...'}
        """
        role = role.lower()
        if role not in ("lecturer", "student"):
            return {"success": False, "error": "role must be 'lecturer' or 'student'."}
        if not email or not password or not identifier:
            return {"success": False, "error": "email, password, and identifier are required."}

        # bcrypt.hashpw requires bytes; it generates and embeds its own salt
        
        password_hash = self._bcrypt.hashpw(
            password.encode("utf-8"),
            self._bcrypt.gensalt(rounds=self.BCRYPT_ROUNDS),
        ).decode("utf-8")   # store as a UTF-8 string in the DB

        conn   = self._connect()
        cursor = conn.cursor()
        try:
            cursor.execute(
                """
                INSERT INTO users (email, password_hash, salt, role, identifier, full_name, created_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s)
                """,
                (email.lower().strip(), password_hash, role,
                 identifier.upper().strip(), time()),
            )
            conn.commit()
            user_id = cursor.lastrowid
        except self._pymysql.IntegrityError:
            return {"success": False, "error": f"An account with email '{email}' already exists."}
        finally:
            cursor.close()
            conn.close()

        return {
            "success": True,
            "user": {
                "id":         user_id,
                "email":      email.lower().strip(),
                "role":       role,
                "identifier": identifier.upper().strip(),
            },
        }

    def authenticate(self, email: str, password: str) -> dict:
        """
        Verify credentials and return the user record on success.

        Returns {'success': True, 'user': {...}} or {'success': False, 'error': '...'}
        """
        conn   = self._connect()
        cursor = conn.cursor()
        try:
            cursor.execute(
                "SELECT * FROM users WHERE email = %s",
                (email.lower().strip(),),
            )
            row = cursor.fetchone()
        finally:
            cursor.close()
            conn.close()

        if not row:
            # Run a dummy bcrypt check to keep response time constant
            # and prevent email enumeration via timing attacks
            self._bcrypt.checkpw(b"dummy", self._bcrypt.hashpw(b"dummy", self._bcrypt.gensalt()))
            return {"success": False, "error": "Invalid email or password."}

        # bcrypt.checkpw is timing-safe and extracts the embedded salt automatically
        if not self._bcrypt.checkpw(password.encode("utf-8"), row["password_hash"].encode("utf-8")):
            return {"success": False, "error": "Invalid email or password."}

        return {
            "success": True,
            "user": {
                "id":         row["id"],
                "email":      row["email"],
                "role":       row["role"],
                "identifier": row["identifier"],
            },
        }


# ══════════════════════════════════════════════════════════════════════════════
# CourseContract  ←  the "smart contract" layer
# ══════════════════════════════════════════════════════════════════════════════
class CourseContract:
    """
    Emulates a smart contract scoped to a single course.

    Each contract:
      - Has its own identity  (course_id, tutor_id)
      - Owns its validation rules  (QR window, duplicate prevention)
      - Reads/writes only its own blocks from the shared ledger
      - Exposes a clean API that mirrors smart-contract method calls

    The shared ledger is injected at construction so all contracts share
    one chain — just like contracts on the same blockchain share the network.
    """

    def __init__(self, course_id: str, tutor_id: str, ledger: "AttendanceBlockchain"):
        self.course_id = course_id
        self.tutor_id  = tutor_id
        self._ledger   = ledger            # shared chain — injected dependency

    # ── Internal state views (read own blocks only) ───────────────────────────

    def _my_sessions(self) -> list[dict]:
        return [
            b.data for b in self._ledger.chain
            if b.data.get("type")      == "session_created"
            and b.data.get("course_id") == self.course_id
            and b.data.get("tutorID")   == self.tutor_id
        ]

    def _session_by_id(self, attendance_id: str) -> dict | None:
        for s in self._my_sessions():
            if s["attendanceID"] == attendance_id:
                return s
        return None

    def _already_signed(self, attendance_id: str, reg_no: str) -> bool:
        for b in self._ledger.chain:
            d = b.data
            if (d.get("type")         == "attendance_signed"
                    and d.get("course_id")   == self.course_id
                    and d.get("attendanceID") == attendance_id
                    and d.get("reg_no")       == reg_no):
                return True
        return False

    # ── Contract methods (public API) ─────────────────────────────────────────

    def create_session(self, attendance_id: str) -> dict:
        """
        Lecturer calls this when generating the QR link.

        Equivalent to calling a 'createSession' function on a smart contract —
        it writes a session_created record bound to THIS course and tutor only.
        """
        if self._session_by_id(attendance_id):
            return {"success": False, "error": f"Session '{attendance_id}' already exists for {self.course_id}."}

        record = {
            "type":         "session_created",
            "tutorID":      self.tutor_id,
            "course_id":    self.course_id,
            "attendanceID": attendance_id,
            "created_at":   time(),
        }
        block = self._ledger._append(record)
        return {"success": True, "block_index": block.index, "session": record}

    def sign_attendance(self, signed_url: str) -> dict:
        """
        Student calls this after scanning the QR and submitting their matric number.

        Validates:
          1. All required URL params present
          2. QR validity timestamp not expired
          3. Session belongs to this contract (tutorID + course_id match)
          4. Session window not expired
          5. Matric number not already recorded for this session
        """
        parsed = urlparse(signed_url)
        params = {k: v[0] for k, v in parse_qs(parsed.query).items()}

        required = {"tutorID", "course_code", "reg_no", "validity"}
        missing  = required - params.keys()
        if missing:
            return {"success": False, "error": f"Missing URL parameters: {missing}"}

        tutor_id    = params["tutorID"]
        course_code = params["course_code"]
        reg_no      = params["reg_no"].upper().strip()
        validity    = float(params["validity"])
        now         = time()

        # Guard: this URL must target THIS contract
        if tutor_id != self.tutor_id or course_code != self.course_id:
            return {
                "success": False,
                "error":   f"URL is for {course_code}/{tutor_id}, not this contract ({self.course_id}/{self.tutor_id}).",
            }

        # 1. QR timestamp expiry
        if now - validity > QR_VALIDITY_WINDOW:
            return {"success": False, "error": f"QR code expired ({QR_VALIDITY_WINDOW // 60} min limit)."}

        # 2. Find the matching session
        # Extract attendanceID from URL path or derive from sessions
        # We match by tutorID + course_id since the session was created that way
        active_sessions = [
            s for s in self._my_sessions()
            if now - s["created_at"] <= QR_VALIDITY_WINDOW
        ]
        if not active_sessions:
            return {"success": False, "error": "No active session found for this course."}

        # Use the most recently created active session
        session       = sorted(active_sessions, key=lambda s: s["created_at"])[-1]
        attendance_id = session["attendanceID"]

        # 3. Duplicate check
        if self._already_signed(attendance_id, reg_no):
            return {"success": False, "error": f"'{reg_no}' already signed for session {attendance_id}."}

        # 4. Record attendance
        record = {
            "type":         "attendance_signed",
            "tutorID":      self.tutor_id,
            "course_id":    self.course_id,
            "attendanceID": attendance_id,
            "reg_no":       reg_no,
            "signed_at":    now,
        }
        block = self._ledger._append(record)
        return {
            "success":     True,
            "message":     f"Attendance recorded for {reg_no} on {self.course_id}.",
            "block_index": block.index,
            "record":      record,
        }

    # ── Query methods (filtering is per-contract, like a smart contract view) ─

    def get_all_attendance(self) -> list[dict]:
        """All signed records for this course across every session."""
        return [
            b.data for b in self._ledger.chain
            if b.data.get("type")      == "attendance_signed"
            and b.data.get("course_id") == self.course_id
        ]

    def get_session_attendance(self, attendance_id: str) -> list[dict]:
        """Signed records for a specific session of this course."""
        return [
            b.data for b in self._ledger.chain
            if b.data.get("type")         == "attendance_signed"
            and b.data.get("course_id")    == self.course_id
            and b.data.get("attendanceID") == attendance_id
        ]

    def get_student_attendance(self, reg_no: str) -> list[dict]:
        """All sessions a specific student attended for this course."""
        return [
            b.data for b in self._ledger.chain
            if b.data.get("type")      == "attendance_signed"
            and b.data.get("course_id") == self.course_id
            and b.data.get("reg_no")    == reg_no.upper().strip()
        ]

    def get_sessions(self) -> list[dict]:
        """All sessions ever created for this course."""
        return self._my_sessions()

    def attendance_summary(self) -> dict:
        """
        Returns a per-student count of sessions attended for this course.
        Useful for the lecturer's dashboard.
        """
        records  = self.get_all_attendance()
        summary  = {}
        for r in records:
            summary[r["reg_no"]] = summary.get(r["reg_no"], 0) + 1
        return {
            "course_id":    self.course_id,
            "total_sessions": len(self.get_sessions()),
            "student_counts": summary,
        }


# ══════════════════════════════════════════════════════════════════════════════
# ContractRegistry  ←  manages all deployed contracts
# ══════════════════════════════════════════════════════════════════════════════
class ContractRegistry:
    """
    Tracks every deployed CourseContract.
    Analogous to a contract factory or registry contract on Ethereum.
    """

    def __init__(self, ledger: "AttendanceBlockchain"):
        self._ledger    = ledger
        self._contracts: dict[str, CourseContract] = {}

    def deploy(self, course_id: str, tutor_id: str) -> CourseContract:
        """
        Deploy a new CourseContract. Raises if the course already has a contract.
        """
        key = f"{course_id}::{tutor_id}"
        if key in self._contracts:
            raise ValueError(f"Contract for {course_id} / {tutor_id} already deployed.")
        contract = CourseContract(course_id, tutor_id, self._ledger)
        self._contracts[key] = contract
        return contract

    def get(self, course_id: str, tutor_id: str) -> CourseContract:
        """Retrieve a previously deployed contract."""
        key = f"{course_id}::{tutor_id}"
        if key not in self._contracts:
            raise KeyError(f"No contract deployed for {course_id} / {tutor_id}. Call deploy() first.")
        return self._contracts[key]

    def get_by_course(self, course_id: str) -> list[CourseContract]:
        """All contracts for a course (multiple tutors possible)."""
        return [c for k, c in self._contracts.items() if k.startswith(f"{course_id}::")]

    def list_all(self) -> list[dict]:
        return [
            {"course_id": c.course_id, "tutor_id": c.tutor_id}
            for c in self._contracts.values()
        ]


# ══════════════════════════════════════════════════════════════════════════════
# AttendanceBlockchain  ←  the shared ledger all contracts write to
# ══════════════════════════════════════════════════════════════════════════════
class AttendanceBlockchain:

    def __init__(self):
        self.storage   = get_storage()
        self.chain     = self.storage.load()
        self.registry  = ContractRegistry(self)
        self.users     = UserStore()

        if not self.chain:
            self.chain = [self._genesis()]
            self.storage.save(self.chain)

    def _genesis(self) -> Block:
        return Block(0, {"type": "genesis", "message": "LASUSTECH e-Attendance Ledger"}, "0")

    def _latest(self) -> Block:
        return self.chain[-1]

    def _append(self, record: dict) -> Block:
        block = Block(len(self.chain), record, self._latest().hash)
        self.chain.append(block)
        self.storage.save(self.chain)
        return block

    def is_chain_valid(self) -> bool:
        for i in range(1, len(self.chain)):
            cur  = self.chain[i]
            prev = self.chain[i - 1]
            if cur.hash != cur.calculate_hash():
                return False
            if cur.previous_hash != prev.hash:
                return False
        return True

    # ── Cross-contract queries (ledger-wide, not per-contract) ────────────────

    def get_student_full_record(self, reg_no: str) -> list[dict]:
        """All attendance records for a student across ALL courses."""
        reg_no = reg_no.upper().strip()
        return [
            b.data for b in self.chain
            if b.data.get("type") == "attendance_signed"
            and b.data.get("reg_no") == reg_no
        ]

    def get_all_signed(self) -> list[dict]:
        """Every attendance_signed block across the whole ledger."""
        return [b.data for b in self.chain if b.data.get("type") == "attendance_signed"]


# ══════════════════════════════════════════════════════════════════════════════
# Demo
# ══════════════════════════════════════════════════════════════════════════════
if __name__ == "__main__":
    ledger = AttendanceBlockchain()

    # ── Deploy contracts (one per course) ─────────────────────────────────────
    csc511 = ledger.registry.deploy("CSC511", "6A0B3354")
    mth401 = ledger.registry.deploy("MTH401", "7B1C4421")

    # ── Lecturers create sessions ─────────────────────────────────────────────
    print("\n── CSC511: create session ──")
    print(json.dumps(csc511.create_session("4A4A23Z"), indent=2))

    print("\n── MTH401: create session ──")
    print(json.dumps(mth401.create_session("5B5B34W"), indent=2))

    ts = int(time())

    # ── Students sign attendance ───────────────────────────────────────────────
    csc_url = f"https://lasustech-eattendance.edu.ng/signed.html?tutorID=6A0B3354&course_code=CSC511&reg_no=LST/20/0042&validity={ts}"
    mth_url = f"https://lasustech-eattendance.edu.ng/signed.html?tutorID=7B1C4421&course_code=MTH401&reg_no=LST/20/0042&validity={ts}"

    print("\n── Student LST/20/0042 signs CSC511 ──")
    print(json.dumps(csc511.sign_attendance(csc_url), indent=2))

    print("\n── Same student signs MTH401 ──")
    print(json.dumps(mth401.sign_attendance(mth_url), indent=2))

    print("\n── Duplicate attempt on CSC511 ──")
    print(json.dumps(csc511.sign_attendance(csc_url), indent=2))

    # ── Per-contract filtering ─────────────────────────────────────────────────
    print("\n── CSC511 attendance summary ──")
    print(json.dumps(csc511.attendance_summary(), indent=2))

    print("\n── MTH401 attendance summary ──")
    print(json.dumps(mth401.attendance_summary(), indent=2))

    # ── Cross-contract: student full record ───────────────────────────────────
    print("\n── LST/20/0042 full record (all courses) ──")
    print(json.dumps(ledger.get_student_full_record("LST/20/0042"), indent=2))

    print("\n── Chain valid:", ledger.is_chain_valid())
    print("── Total blocks:", len(ledger.chain))
    print("── Contracts deployed:", ledger.registry.list_all())
