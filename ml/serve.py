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

@app.route("/chat", methods=["POST"])
def handle_chat():
    data = request.get_json(silent=True) or {}
    message = data.get("message") or data.get("text") or data.get("prompt") or ""
    history = data.get("history") or []

    if not message.strip():
        return jsonify({"success": False, "message": "Message cannot be empty"}), 400

    msg_lower = message.strip().lower()

    # Conversational intent detection
    if any(msg_lower.startswith(g) for g in ["hi", "hello", "hey", "greetings", "good morning", "good evening"]):
        reply = (
            "Hello! 👋 I'm **FactScan AI**, your intelligent conversational assistant. "
            "You can ask me questions, request news analyses, or chat about any topic. How can I assist you today?"
        )
        return jsonify({
            "success": True,
            "reply": reply,
            "isReal": None,
            "confidence": None,
            "engine": "python_copilot_conversational"
        })

    # Run Python Ensemble prediction on the content
    ml_res = predict(message)
    is_real = ml_res.get("isReal")
    conf = ml_res.get("confidence", 90)
    signals = ml_res.get("warningSignals", [])

    verdict_str = "Verified Authentic / Credible" if is_real else "Unverified / Misleading Hoax"
    icon = "✅" if is_real else "⚠️"

    reply = (
        f"{icon} **Analysis Result: {verdict_str}** ({conf}% confidence)\n\n"
        f"FactScan AI's multi-branch deep learning ensemble evaluated this statement based on "
        f"linguistic indicators, institutional corroboration, and viral debunking patterns.\n\n"
    )
    if signals:
        reply += "**Detected Indicators:**\n• " + "\n• ".join(signals) + "\n\n"
    
    reply += (
        "**Context:** " + (
            "Statements with this linguistic structure typically correspond with accredited journalistic releases."
            if is_real else
            "This phrasing exhibits sensationalist or uncorroborated characteristics typical of online viral rumors. Check primary sources before sharing."
        )
    )

    return jsonify({
        "success": True,
        "reply": reply,
        "isReal": is_real,
        "confidence": conf,
        "warningSignals": signals,
        "engine": "python_copilot_ensemble"
    })

if __name__ == "__main__":
    print("[*] Starting FactScan AI ML Microservice on http://127.0.0.1:5000 ...")
    app.run(host="127.0.0.1", port=5000, debug=False)

