from flask import Flask, jsonify, request


#setup
app = Flask(__name__)
CORS(app, origins=["http://localhost:3000"], supports_credentials=True)