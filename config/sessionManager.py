import os
import time
import shelve
import secrets
from http.cookies import SimpleCookie
from typing import Any, Optional
from contextlib import contextmanager


class SessionManager:
    def __init__(self, expire_time: int = 1800, session_dir: str = "sessions"):
        """
        Initialize SessionManager
        
        Args:
            expire_time: Session lifetime in seconds (default: 1800 = 30 minutes)
            session_dir: Directory to store session files
        """
        self.expire_time = expire_time
        self.session_dir = session_dir
        self.session_id: Optional[str] = None
        self.session_data: dict = {}
        self.created = False
        
        # Create session directory if it doesn't exist
        os.makedirs(self.session_dir, exist_ok=True)
    
    def _get_session_path(self, session_id: str) -> str:
        """Get file path for a session"""
        return os.path.join(self.session_dir, f"session_{session_id}.db")
    
    def _generate_session_id(self) -> str:
        """Generate a cryptographically secure session ID"""
        return secrets.token_urlsafe(32)
    
    def _load_session(self, session_id: str) -> dict:
        """Load session data from disk"""
        session_path = self._get_session_path(session_id)
        
        if os.path.exists(session_path):
            try:
                with shelve.open(session_path) as db:
                    return dict(db)
            except Exception:
                return {}
        return {}
    
    def _save_session(self) -> None:
        """Save current session data to disk"""
        if not self.session_id:
            return
        
        session_path = self._get_session_path(self.session_id)
        
        with shelve.open(session_path) as db:
            for key, value in self.session_data.items():
                db[key] = value
    
    def _delete_session_file(self) -> None:
        """Delete session file from disk"""
        if not self.session_id:
            return
        
        session_path = self._get_session_path(self.session_id)
        if os.path.exists(session_path):
            try:
                os.remove(session_path)
            except Exception:
                pass
    
    def start(self, session_id: Optional[str] = None) -> str:
        """
        Start or resume a session
        
        Args:
            session_id: Optional existing session ID from cookie
        
        Returns:
            Current session ID
        """
        if session_id:
            # Verify session ID exists and is valid
            session_path = self._get_session_path(session_id)
            if os.path.exists(session_path):
                self.session_id = session_id
                self.session_data = self._load_session(session_id)
            else:
                self.session_id = self._generate_session_id()
                self.session_data = {}
        else:
            self.session_id = self._generate_session_id()
            self.session_data = {}
        
        # Initialize session metadata if not set
        if 'created' not in self.session_data:
            self.session_data['created'] = time.time()
            self.session_data['last_regeneration'] = time.time()
            self.session_data['expire'] = time.time() + self.expire_time
        
        # Sliding expiration (refresh on activity)
        self.session_data['expire'] = time.time() + self.expire_time
        
        # Regenerate ID every 5 minutes (configurable)
        self._maybe_regenerate_id(300)
        
        self._save_session()
        return self.session_id
    
    def _maybe_regenerate_id(self, interval: int) -> None:
        """Regenerate session ID periodically for security"""
        if 'last_regeneration' not in self.session_data:
            self.session_data['last_regeneration'] = time.time()
            return
        
        if time.time() - self.session_data['last_regeneration'] >= interval:
            # Generate new ID but keep the same data
            old_id = self.session_id
            self.session_id = self._generate_session_id()
            
            # Copy data to new session file
            self._save_session()
            
            # Delete old session file
            if old_id:
                old_path = self._get_session_path(old_id)
                if os.path.exists(old_path):
                    try:
                        os.remove(old_path)
                    except Exception:
                        pass
            
            self.session_data['last_regeneration'] = time.time()
            self._save_session()
    
    def set(self, key: str, value: Any) -> None:
        """Set a session value"""
        self._ensure_session()
        self.session_data[key] = value
        self._save_session()
    
    def get(self, key: str, default: Any = None) -> Any:
        """Get a session value"""
        self._ensure_session()
        return self.session_data.get(key, default)
    
    def is_expired(self) -> bool:
        """Check if session has expired"""
        self._ensure_session()
        
        if 'expire' in self.session_data and time.time() > self.session_data['expire']:
            self.destroy()
            return True
        
        return False
    
    def remove(self, key: str) -> None:
        """Remove a session key"""
        self._ensure_session()
        if key in self.session_data:
            del self.session_data[key]
            self._save_session()
    
    def destroy(self) -> None:
        """Destroy the session completely"""
        if self.session_id:
            self._delete_session_file()
        
        self.session_id = None
        self.session_data = {}
        self.created = False
    
    def get_cookie_attributes(self) -> dict:
        """Get cookie attributes for setting the session cookie"""
        secure = os.environ.get('HTTPS', '').lower() == 'on'
        
        return {
            'lifetime': self.expire_time,
            'path': '/',
            'domain': '',  # Set your domain if needed
            'secure': secure,
            'httponly': True,
            'samesite': 'Strict'
        }
    
    def create_cookie_header(self) -> str:
        """Create the Set-Cookie header for the session"""
        if not self.session_id:
            return ''
        
        attrs = self.get_cookie_attributes()
        cookie = SimpleCookie()
        cookie['session_id'] = self.session_id
        cookie['session_id']['path'] = attrs['path']
        cookie['session_id']['httponly'] = attrs['httponly']
        cookie['session_id']['samesite'] = attrs['samesite']
        
        if attrs['secure']:
            cookie['session_id']['secure'] = True
        
        if attrs['domain']:
            cookie['session_id']['domain'] = attrs['domain']
        
        # Max-Age is set in seconds
        cookie['session_id']['max-age'] = attrs['lifetime']
        
        return cookie.output(header='').strip()
    
    def _ensure_session(self) -> None:
        """Ensure session is started before operations"""
        if self.session_id is None:
            self.start()


# Example WSGI middleware integration
class SessionMiddleware:
    """WSGI middleware to handle session cookies automatically"""
    
    def __init__(self, app, expire_time: int = 1800):
        self.app = app
        self.session_manager = SessionManager(expire_time)
    
    def __call__(self, environ, start_response):
        # Extract session ID from cookies
        session_id = None
        if 'HTTP_COOKIE' in environ:
            cookie = SimpleCookie(environ['HTTP_COOKIE'])
            if 'session_id' in cookie:
                session_id = cookie['session_id'].value
        
        # Start session
        session_id = self.session_manager.start(session_id)
        
        # Store session manager in environ for app access
        environ['session_manager'] = self.session_manager
        
        def custom_start_response(status, headers, exc_info=None):
            # Add session cookie header
            cookie_header = self.session_manager.create_cookie_header()
            if cookie_header:
                headers.append(('Set-Cookie', cookie_header))
            return start_response(status, headers, exc_info)
        
        return self.app(environ, custom_start_response)


# Usage example with a simple WSGI app
def example_app(environ, start_response):
    """Example WSGI application using session"""
    session = environ['session_manager']
    
    # Check expiration
    if session.is_expired():
        start_response('401 Unauthorized', [('Content-Type', 'text/plain')])
        return [b'Session expired']
    
    # Increment visit counter
    visits = session.get('visits', 0)
    visits += 1
    session.set('visits', visits)
    
    # Get user data if exists
    username = session.get('username', 'Guest')
    
    response_body = f"Hello {username}! You've visited {visits} times."
    
    start_response('200 OK', [('Content-Type', 'text/plain')])
    return [response_body.encode('utf-8')]


if __name__ == '__main__':
    # Standalone usage without WSGI
    session_mgr = SessionManager(expire_time=1800)
    
    # Start session
    session_id = session_mgr.start()
    print(f"Session ID: {session_id}")
    
    # Set some values
    session_mgr.set('username', 'JohnDoe')
    session_mgr.set('user_id', 12345)
    
    # Get values
    username = session_mgr.get('username')
    user_id = session_mgr.get('user_id')
    
    print(f"Username: {username}, User ID: {user_id}")
    
    # Check expiration
    if not session_mgr.is_expired():
        print("Session is active")
    
    # Get cookie header for browser
    cookie_header = session_mgr.create_cookie_header()
    print(f"Cookie header: {cookie_header}")
    
    # Remove a key
    session_mgr.remove('user_id')
    
    # Destroy session
    # session_mgr.destroy()