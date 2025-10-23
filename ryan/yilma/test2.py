import mysql.connector

try:
    conn = mysql.connector.connect(
        host="localhost",
        user="root",
        password="ics311",
        database="UniversityDB"
    )
    print("? Connected successfully")
    conn.close()
except mysql.connector.Error as err:
    print("? Error:", err)