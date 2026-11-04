import re
import numpy as np
from sklearn.base import BaseEstimator, TransformerMixin

SENSATIONAL_WORDS = {
    "shocking", "miracle", "mind-blowing", "exposed", "secret", "unbelievable",
    "viral", "banned", "truth about", "breaking alert", "unreal", "magic",
    "cure overnight", "hidden truth", "insane", "you won't believe", "conspiracy"
}

URGENCY_PHRASES = [
    "forward this", "share before deleted", "warn everyone", "urgent alert",
    "immediate action", "don't ignore", "hurry up", "emergency warning",
    "forward to", "spread this", "alert your family", "share with 50", "share with 20"
]

ATTRIBUTION_TERMS = {
    "according", "announced", "statement", "reported", "published", "study",
    "researchers", "ministry", "spokesperson", "release", "reuters", "confirmed",
    "journal", "scientists", "official", "documented", "validated", "peer-reviewed",
    "directorate", "department", "authorities", "organization", "observatory",
    "council", "delegates", "committee", "commissioners", "investigators"
}

VAGUE_SPECULATION = [
    "sources say", "people claim", "rumored", "alleged", "believed to be",
    "someone said", "they claim", "word is", "it is said", "unnamed sources"
]

FEAR_MONGERING_TERMS = {
    "deadly", "lethal", "catastrophic", "toxic", "poison", "fatal",
    "apocalypse", "wipe out", "doom", "destroy", "perish", "devastating",
    "extinction", "biohazard", "emergency shutdown"
}

DEFINITIVE_BIAS_PHRASES = [
    "100 percent", "guaranteed", "proven fact", "undeniable", "permanent cure",
    "miracle remedy", "secret revealed", "totally destroys", "miracle juice",
    "doctors don't want you to know", "cure all"
]

class LinguisticFeatureExtractor(BaseEstimator, TransformerMixin):
    """
    Extracts 12 high-signal stylistic, linguistic, and credibility features
    from raw news claims and articles for advanced NLP classification.
    """
    def __init__(self):
        pass

    def fit(self, X, y=None):
        return self

    def extract_features_single(self, text):
        text_str = str(text) if text else ""
        text_lower = text_str.lower()
        words = re.findall(r'\b\w+\b', text_lower)
        total_words = max(len(words), 1)

        # 0. Sensationalism Score
        sensational_count = sum(1 for w in words if w in SENSATIONAL_WORDS)
        sensational_score = min(1.0, (sensational_count / total_words) * 10)

        # 1. Urgency / Viral Forward Score
        urgency_count = sum(1 for phrase in URGENCY_PHRASES if phrase in text_lower)
        urgency_score = min(1.0, urgency_count * 0.4)

        # 2. Institutional Attribution Score
        attribution_count = sum(1 for w in words if w in ATTRIBUTION_TERMS)
        attribution_score = min(1.0, (attribution_count / total_words) * 8)

        # 3. Vague Speculation Score
        vague_count = sum(1 for phrase in VAGUE_SPECULATION if phrase in text_lower)
        vague_score = min(1.0, vague_count * 0.3)

        # 4. Caps Exaggeration Ratio (uppercase letters in words)
        raw_words = re.findall(r'\b[A-Za-z]+\b', text_str)
        caps_words = sum(1 for w in raw_words if len(w) > 2 and w.isupper())
        caps_ratio = min(1.0, caps_words / max(len(raw_words), 1))

        # 5. Excessive Punctuation Density (! and ?)
        punct_count = len(re.findall(r'[!?]{1,}', text_str))
        punct_density = min(1.0, punct_count / total_words)

        # 6. Numerical & Statistical Density
        numbers = re.findall(r'\b\d+(?:\.\d+)?%?\b', text_str)
        num_density = min(1.0, len(numbers) / total_words)

        # 7. Lexical Diversity (Type-Token Ratio)
        lex_div = len(set(words)) / total_words

        # 8. Average Word Length (Journalistic text has higher avg word length)
        avg_len = sum(len(w) for w in words) / total_words
        norm_len = min(1.0, max(0.0, (avg_len - 3.5) / 5.0))

        # 9. Direct Quotes Presence
        quote_count = len(re.findall(r'["\u201c\u201d\u2018\u2019]', text_str))
        quote_score = min(1.0, quote_count / 4.0)

        # 10. Fear-Mongering Score
        fear_count = sum(1 for w in words if w in FEAR_MONGERING_TERMS)
        fear_score = min(1.0, (fear_count / total_words) * 8)

        # 11. Absolute Definitive Certainty Bias
        bias_count = sum(1 for phrase in DEFINITIVE_BIAS_PHRASES if phrase in text_lower)
        bias_score = min(1.0, bias_count * 0.35)

        return [
            sensational_score,
            urgency_score,
            attribution_score,
            vague_score,
            caps_ratio,
            punct_density,
            num_density,
            lex_div,
            norm_len,
            quote_score,
            fear_score,
            bias_score
        ]

    def transform(self, X):
        features = [self.extract_features_single(text) for text in X]
        return np.array(features, dtype=np.float32)

    @classmethod
    def get_feature_dict(cls, text):
        inst = cls()
        feats = inst.extract_features_single(text)
        return {
            "sensationalismIndex": int(round(feats[0] * 100)),
            "urgencyPanicLevel": int(round(feats[1] * 100)),
            "institutionalAttribution": int(round(feats[2] * 100)),
            "vagueSpeculation": int(round(feats[3] * 100)),
            "excessivePunctuation": int(round(feats[5] * 100)),
            "factualNumericalDensity": int(round(feats[6] * 100)),
            "lexicalDiversity": int(round(feats[7] * 100)),
            "fearMongeringLevel": int(round(feats[10] * 100)),
            "definitiveCertaintyBias": int(round(feats[11] * 100))
        }
