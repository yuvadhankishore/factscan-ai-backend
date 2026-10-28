#!/usr/bin/env python3
"""
FactScan AI - Lightweight Flask ML Prediction Microservice
Runs on localhost:5000.
Keeps the model and vectorizer in memory for sub-millisecond response times.
"""

from flask import Flask, request, jsonify
from predict import predict

app = Flask(__name__)

@app.route("/health", methods=["GET"])
def health():
    return jsonify({"status": "healthy", "service": "FactScan AI ML Service"})

@app.route("/predict", methods=["POST"])
def handle_predict():
    data = request.get_json(silent=True) or {}
    text = data.get("text") or data.get("input") or data.get("claim") or ""
    
    if not text:
        return jsonify({"success": False, "message": "Field 'text' or 'input' is required"}), 400

    result = predict(text)
    return jsonify(result)

if __name__ == "__main__":
    print("[*] Starting FactScan AI ML Microservice on http://127.0.0.1:5000 ...")
    app.run(host="127.0.0.1", port=5000, debug=False)
