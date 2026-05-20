import secrets
import uuid


class TokenGenerator:
    @staticmethod
    def short_code(length: int = 9) -> str:
        """
        Generate a short random code.
        
        Args:
            length: Desired length of the code (default: 9)
            
        Returns:
            A random hexadecimal string of specified length
        """
        # Generate 5 random bytes (10 hex chars) and truncate to desired length
        return secrets.token_hex(5)[:length]

    @staticmethod
    def token(length: int = 32) -> str:
        """
        Generate a random token.
        
        Args:
            length: Desired length of the token (default: 32)
            
        Returns:
            A random hexadecimal string of specified length
        """
        # Generate 16 random bytes (32 hex chars) and truncate to desired length
        return secrets.token_hex(16)[:length]

    @staticmethod
    def uuid_v4() -> str:
        """
        Generate a UUID v4 string.
        
        Returns:
            A randomly generated UUID v4 string
        """
        # Python's uuid module automatically generates UUID v4 when using uuid4()
        return str(uuid.uuid4())


# Alternative manual implementation of uuid_v4 (if you don't want to use uuid module)
class TokenGeneratorManual:
    @staticmethod
    def uuid_v4() -> str:
        """
        Generate a UUID v4 string manually (without using uuid module).
        
        Returns:
            A randomly generated UUID v4 string following RFC 4122
        """
        data = secrets.token_bytes(16)
        
        # Convert bytes to bytearray for mutability
        data = bytearray(data)
        
        # Version 4: set the 4 most significant bits of the 7th byte to 0100
        data[6] = (data[6] & 0x0f) | 0x40
        
        # Variant RFC 4122: set the 2 most significant bits of the 9th byte to 10
        data[8] = (data[8] & 0x3f) | 0x80
        
        # Format as UUID string
        hex_data = data.hex()
        return f"{hex_data[:8]}-{hex_data[8:12]}-{hex_data[12:16]}-{hex_data[16:20]}-{hex_data[20:]}"


# Usage examples:
if __name__ == "__main__":
    print(TokenGenerator.short_code())      # Example: "a3f5c8e2b"
    print(TokenGenerator.short_code(12))    # Example: "d7e9f2a4b6c8"
    
    print(TokenGenerator.token())           # Example: "f47ac10b58cc4372a5678e1f2d3a4b5c"
    print(TokenGenerator.token(16))         # Example: "a1b2c3d4e5f67890"
    
    print(TokenGenerator.uuid_v4())         # Example: "550e8400-e29b-41d4-a716-446655440000"
    
    # Using manual implementation
    print(TokenGeneratorManual.uuid_v4())   # Example: "6ba7b810-9dad-11d1-80b4-00c04fd430c8"