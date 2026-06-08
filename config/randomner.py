import secrets
import os

class RandomHelper:
    @staticmethod
    def hex(bytes_count: int, length: int = None) -> str:
        """
        Generate random hex string.
        
        Args:
            bytes_count: Number of random bytes to generate
            length: Optional length to truncate the hex string to
        
        Returns:
            Random hex string
        """
        hex_string = secrets.token_hex(bytes_count)
        return hex_string[:length] if length is not None else hex_string
    
    @staticmethod
    def bytes(length: int) -> bytes:
        """
        Generate random bytes.
        
        Args:
            length: Number of random bytes to generate
        
        Returns:
            Random bytes object
        """
        return os.urandom(length)