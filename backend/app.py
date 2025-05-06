from flask import Flask, request, jsonify
from flask_cors import CORS
import sqlite3

app = Flask(__name__)
CORS(app)

# Database init
def init_db():
    conn = sqlite3.connect('database.db')
    c = conn.cursor()
    c.execute('''CREATE TABLE IF NOT EXISTS posts (id INTEGER PRIMARY KEY, content TEXT)''')
    conn.commit()
    conn.close()

@app.route('/posts', methods=['GET'])
def get_posts():
    conn = sqlite3.connect('database.db')
    c = conn.cursor()
    c.execute('SELECT * FROM posts')
    posts = [{'id': row[0], 'content': row[1]} for row in c.fetchall()]
    conn.close()
    return jsonify(posts)

@app.route('/posts', methods=['POST'])
def add_post():
    content = request.json.get('content')
    conn = sqlite3.connect('database.db')
    c = conn.cursor()
    c.execute('INSERT INTO posts (content) VALUES (?)', (content,))
    conn.commit()
    conn.close()
    return jsonify({'message': 'Post added'}), 201

if __name__ == '__main__':
    init_db()
    app.run(debug=True)
