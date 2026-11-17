# FactScan AI - High-Accuracy News Verification & Fact Analyzer Backend

FactScan AI features a next-generation, multi-source verification and entity truth-checking architecture designed to analyze any news story in the world against live international wire coverage, canonical geopolitical encyclopedias, and multi-agent AI.

## Architectural Layers

1. **Multi-Source News Engine (`ml/multi_source_engine.py`):**
   - Synthesizes intelligent search queries from arbitrary text.
   - Concurrently aggregates articles via Google News RSS and direct HTML scrapers.
   - Strips boilerplate navigation, ads, and footers.

2. **Geopolitical & Truth Entity Verifier (`ml/geography_verifier.py`, `ml/entity_fact_checker.py`):**
   - Sovereign nation vs. state/province auditor (e.g. catches assertions like "India is a state" or "Paris is a country").
   - Capital city and national boundary verification.
   - Documented scientific misconception debunking (flat earth, vaccine myths, brain usage myths).

3. **Wikipedia Knowledge Grounding (`ml/wiki_verifier.py`):**
   - Real-time Wikipedia REST API integration for canonical encyclopedic classification.

4. **Stance & Semantic Alignment Analyzer (`ml/stance_analyzer.py`):**
   - Classifies external coverage into `CORROBORATES`, `REFUTES`, or `NEUTRAL_COVERAGE`.
   - Incorporates entity contradiction detection.

5. **Multi-Source Consensus Scorer (`ml/consensus_scorer.py`):**
   - Computes weighted 0–100% consensus scores based on publisher credibility tiers (Tier 1 Wires, Fact-Checkers, High Factuality, Tabloids).

6. **Unified AI Agent Orchestrator (`config/ai_agent_orchestrator.php`):**
   - Coordinates Google Gemini 1.5/2.0 Flash with Google Search Grounding.
   - OpenAI ChatGPT (GPT-4o-mini) with live multi-source web context injection.
   - Python Soft-Voting Ensemble (Calibrated LinearSVC + Logistic Regression + ComplementNB).

## Key API Endpoints

- `POST /factscanai_backend/api/factcheck.php` - Primary multi-engine verification endpoint.
- `POST /factscanai_backend/api/multi_source_check.php` - Dedicated on-demand cross-referencing endpoint.
- `GET  /factscanai_backend/api/trending.php` - Curated and dynamic trending claims with consensus stats.
- `GET  /factscanai_backend/api/saved.php` - Bookmarked verifications with multi-source snapshots.
- `POST /factscanai_backend/api/chat.php` - Contextual fact-checking AI chat assistant.
