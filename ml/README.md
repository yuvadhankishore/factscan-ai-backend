# FactScan AI - Machine Learning Engine & Datasets Guide

This folder contains the Python Machine Learning pipeline for training, evaluating, and serving AI fake news detection models for **FactScan AI**, integrated directly with the PHP backend.

---

## 1. Top Recommended Fact-Checking & Fake News Datasets

Here are the best benchmark datasets available for training fake news detection and fact-checking models:

### 1. WELFake Dataset (Recommended for Production)
* **Size**: 72,134 news articles (35,028 Real, 37,106 Fake).
* **Format**: CSV (`title`, `text`, `label` where `1 = Real`, `0 = Fake`).
* **Why it's great**: Merged from 4 prominent datasets (Kaggle, McIntire, Reuters, BuzzFeed), perfectly balanced, clean text, and modern.
* **Download**: [Kaggle WELFake Dataset](https://www.kaggle.com/datasets/saurabhshahane/fake-news-classification) or IEEE Dataport.

### 2. LIAR Benchmark Dataset
* **Size**: 12,836 short human-verified statements collected from PolitiFact.
* **Format**: TSV (`ID`, `label`, `statement`, `subject`, `speaker`, `job`, `state`, `party`, `context`).
* **Labels**: 6 fine-grained truth levels (`pants-fire`, `false`, `barely-true`, `half-true`, `mostly-true`, `true`).
* **Why it's great**: Specifically designed for short political claims and viral text statements, matching mobile app input perfectly.
* **Download**: [Hugging Face Datasets: `liar`](https://huggingface.co/datasets/liar) or [GitHub `tobivw/liar_dataset`](https://github.com/tobivw/liar_dataset).

### 3. ISOT Fake News Dataset
* **Size**: 44,898 articles (21,417 Real from Reuters, 23,481 Fake from flagged websites).
* **Format**: 2 CSV files (`True.csv` and `Fake.csv`).
* **Download**: [University of Victoria ISOT Lab](https://www.uvic.ca/engineering/ece/isot/datasets/fake-news/index.php) or [Kaggle ISOT Dataset](https://www.kaggle.com/datasets/clmentbisaillon/fake-and-real-news-dataset).

### 4. FEVER (Fact Extraction and VERification)
* **Size**: 185,445 claims cross-referenced with 5.4 million Wikipedia articles.
* **Format**: JSONL.
* **Labels**: `SUPPORTS`, `REFUTES`, `NOT ENOUGH INFO`.
* **Download**: [FEVER Official Site](https://fever.ai/resources.html).

---

## 2. Quickstart & Setup

### Step 1: Install Python & Dependencies
Ensure Python 3.9+ is installed on your machine. Open a terminal and run:
```powershell
cd C:\xampp\htdocs\factscanai_backend\ml
pip install -r requirements.txt
```

### Step 2: Train the Model
A starter seed dataset is included in `data/sample_dataset.csv`. You can train the initial model right away:
```powershell
python train.py
```
To train on a downloaded dataset (like WELFake):
```powershell
python train.py --data "path/to/WELFake_Dataset.csv"
```
This generates:
- `model/model.joblib`: Trained classification model
- `model/vectorizer.joblib`: TF-IDF feature vocabulary

### Step 3: Test Prediction CLI
Test the prediction engine from command line:
```powershell
python predict.py --text "NASA confirms 2024 was warmest year on record"
```

### Step 4: Run the High-Performance Microservice (Optional)
To achieve sub-millisecond predictions without re-loading Python on every request:
```powershell
python serve.py
```
This runs a local Flask service on `http://127.0.0.1:5000`.

---

## 3. How the PHP Backend Integrates with Python

The PHP backend includes [`config/ai_bridge.php`](../config/ai_bridge.php) which connects [`api/factcheck.php`](../api/factcheck.php) to Python:

```
                  +----------------------------------------+
                  |  Android App / Postman Client          |
                  +-------------------+--------------------+
                                      | POST /api/factcheck.php
                                      v
                        +-------------+------------+
                        |  PHP Backend             |
                        |  (config/ai_bridge.php)  |
                        +-------------+------------+
                                      |
             +------------------------+------------------------+
             | (1) Try Microservice   | (2) Try CLI Script     | (3) Fallback
             v                        v                        v
    +-------------------+    +--------------------+    +---------------+
    | http://127.0.0.1  |    | python predict.py  |    | Gemini AI /   |
    | :5000/predict     |    | (Direct Process)   |    | Rule Engine   |
    +-------------------+    +--------------------+    +---------------+
```

1. **Microservice**: If `serve.py` is running, PHP sends the claim via fast cURL.
2. **CLI**: If the microservice is not running, PHP executes `python ml/predict.py` via `proc_open` pipes.
3. **Graceful Fallback**: If Python is not yet installed or the model is not yet trained, the PHP backend automatically falls back to **Gemini 1.5 Flash** and the built-in rule analyzer so your mobile app **never crashes or hangs**.
