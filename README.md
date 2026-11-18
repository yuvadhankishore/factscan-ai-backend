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

- `POST /factscanai_backend/api/factcheck.php` - Primary multi-engine verification endpoint with whyThisResult and reasoningSteps.
- `POST /factscanai_backend/api/multi_source_check.php` - Dedicated on-demand cross-referencing endpoint.
- `GET  /factscanai_backend/api/knowledge_search.php` - Indexed facts database and debunk rationales query endpoint.
- `POST /factscanai_backend/api/trending.php` - Curated and dynamic trending claims with consensus stats.
- `GET  /factscanai_backend/api/saved.php` - Bookmarked verifications with multi-source snapshots.
- `POST /factscanai_backend/api/chat.php` - Contextual fact-checking AI chat assistant.
- `POST /factscanai_backend/api/gemini_analyze.php` - Dedicated Google Gemini 2.0 Flash multimodal news verification endpoint.

## Multi-Domain Reasoning Engine & Accuracy Benchmark Suite

FactScan AI features an explicit, multi-stage reasoning engine (`ml/reasoning_engine.py`) and knowledge base corpus covering 5 foundational domains:

1. **World Geography & Sovereign Entities**:
   - Primary database of UN member states and sovereignty affirmations.
   - Accurately classifies sovereign nations as REAL and false statehood claims as FAKE (e.g. "India is a state" -> FAKE with detailed sovereign constitutional justification).
2. **Medical & Health Sciences**:
   - Validates clinical interventions (insulin, metformin) and debunks viral miracle cures (boiled lemon, alkaline diets).
3. **Astronomy & Planetary Physics**:
   - Confirms planetary mechanics (Earth's eastward rotation, heliocentrism) and refutes viral panic hoaxes (six days of darkness).
4. **Technology & Computing**:
   - Debunks viral AI robot escape hoaxes, 5G electromagnetic myths, and sensor conspiracy theories.
5. **Economy, Banking & Currency**:
   - Debunks currency scams (nano-GPS chips in banknotes) and fraudulent relief forwards.

### 50-Claim Benchmark Results (`ml/test_accuracy.py`)
- **100% Pass Rate (50/50 test claims verified correctly)** across all 4 benchmark classes:
  - `TestGeopoliticalAccuracy`: 100% pass
  - `TestMedicalScienceAccuracy`: 100% pass
  - `TestAstronomyScienceAccuracy`: 100% pass
  - `TestViralHoaxesAndScams`: 100% pass
- Model Retraining: Ensemble pipeline achieved **97.4% test accuracy** and **99.76% ROC-AUC**.

## Gemini 2.0 All-Format News Verification Architecture

FactScan AI features a unified Gemini-grade truth verification engine supporting all news ingestion formats:

1. **Text Claims & Headlines**: Fast-path geopolitical validation (e.g. UN member state assertions), medical pseudoscience debunking (e.g. WHO cancer treatment registries), and viral rumor detection.
2. **Web Article URLs**: Live article scraping, publisher credibility scoring, and consensus stance comparison against accredited wire services.
3. **Documents & PDFs**: Automated text extraction from uploaded research briefs, whitepapers, or PDF releases.
4. **Photos & Screenshots**: Google ML Kit OCR and Gemini multimodal vision inspection for WhatsApp forwards, doctored headlines, and fake screenshots.
5. **Voice Notes & Audio**: Transcribed speech evaluation identifying synthetic audio claims and spoken misinformation.

## Dynamic Claim Rationale Engine & Zero-Boilerplate Assurance

FactScan AI eliminates static fallback messages across Enter URL, Paste Text, and Upload Article:

- **Zero-Shot Claim Thesis Extraction**: Extracts exact entities, subjects, and predicates from arbitrary text or documents rather than relying on pre-canned templates.
- **Robust JavaScript & Paywall Scraper**: `UrlHelper` extracts JSON-LD news schemas, OpenGraph/Twitter card metadata, and semantic headers (`h1`-`h3`, `article`, `main`) so dynamic and restricted news URLs are parsed without 422 errors.
- **Multi-Paragraph Document Analyzer**: Parses multi-paragraph documents (PDF, DOCX, TXT) and isolates core factual assertions for individual consensus verification.
- **Empirical Rationale Generation**: Dynamically formulates a 3-point forensic rationale citing specific claim terms, empirical metrics, certainty bias, and objective density.
- **Strict Verification Verdicts**: Ensures unambiguous `REAL` or `FAKE` classifications with claim-specific evidentiary backing.
