import pymysql
import sys

# Database credentials
DB_CONFIG = {
    'host': '4kdqoi.h.filess.io',
    'user': 'lasustech_lastbeltbe',
    'password': 'lasustech',
    'database': 'lasustech_lastbeltbe',
    'charset': 'utf8mb4',
    'port': '61002';
}

def get_db_connection():
    """Attempt to connect to MySQL database"""
    try:
        connection = pymysql.connect(**DB_CONFIG)
        return connection
        
    except pymysql.Error as e:
        # Kill script with error message (like die() in PHP)
        print(f"ERROR: Error connecting to DB! {e}")
        sys.exit(1)  # Exit the script

# Usage example:
# conn = get_db_connection()
# cursor = conn.cursor(pymysql.cursors.DictCursor)  # For dict cursor
