#!/usr/bin/env python3
"""
FactScan AI - Clinical Medicine & Epidemiology Training Corpus
Peer-reviewed clinical evidence versus viral medical hoaxes and dangerous pseudoscience.
"""

MEDICINE_CORPUS = [
    # --- REAL NEWS (1) ---
    (
        "The Lancet publishes multicenter phase 3 trial on targeted CAR-T cell immunotherapy for lymphoma",
        "Clinical oncologists across 18 academic research hospitals documented an 82 percent objective response rate in refractory B-cell lymphoma patients receiving genetically modified autologous CAR-T cell infusion, meeting primary survival endpoints.",
        1
    ),
    (
        "World Health Organization prequalifies novel low-cost oral cholera vaccine for emergency stockpiles",
        "The WHO announced the prequalification of a simplified oral cholera vaccine formulation manufactured by EuBiologics, significantly expanding global vaccine supply to mitigate cholera flare-ups in conflict and climate-stressed zones.",
        1
    ),
    (
        "New England Journal of Medicine study verifies cardiovascular safety of dual GLP-1 and GIP receptor agonists",
        "In a double-blind, randomized clinical trial enrolling over 13,000 adult participants with type 2 diabetes, researchers documented a 16 percent reduction in major adverse cardiovascular events compared to standard care over a three-year follow-up.",
        1
    ),
    (
        "FDA grants full approval for oral antiviral medication against severe influenza complications",
        "The Food and Drug Administration issued full regulatory approval for a selective cap-dependent endonuclease inhibitor after controlled trials established significant reduction in duration of fever and viral shedding in high-risk patients.",
        1
    ),
    (
        "National Institutes of Health launches clinical trial evaluating universal mRNA coronavirus vaccine",
        "The National Institute of Allergy and Infectious Diseases commenced human phase 1 safety trials evaluating a pan-coronavirus nanoparticle vaccine designed to generate broad neutralizing antibodies against diverse sarbecovirus lineages.",
        1
    ),
    (
        "ICMR issues standardized clinical guidelines for early detection of cervical dysplasia",
        "The Indian Council of Medical Research published updated screening directives recommending molecular HPV DNA testing alongside visual inspection with acetic acid for primary healthcare clinics across rural and urban centers.",
        1
    ),

    # --- FAKE NEWS / DEBUNKED (0) ---
    (
        "Drinking hot saltwater and baking soda cures terminal renal failure in four days",
        "Viral alternative health blogs allege that ingesting concentrated saline with sodium bicarbonate flushes cellular toxins and regenerates necrotic kidney nephrons without hemodialysis. Nephrology associations warn acute sodium loading causes fatal hypertensive crises and pulmonary edema.",
        0
    ),
    (
        "Crushed raw garlic cloves applied to forehead immediately dissolves acute stroke blood clots",
        "Forwarded medical advice on social messaging apps claims that applying garlic paste over the temples breaks up intracranial ischemic thrombi within fifteen minutes. Neurologists stress that acute stroke requires immediate emergency thrombolytic hospital therapy.",
        0
    ),
    (
        "Boiling guava leaves with turmeric eliminates type 1 diabetes permanently on first dose",
        "Social media videos promise a permanent cure for juvenile autoimmune diabetes by drinking boiled guava leaves and raw turmeric roots. Endocrinologists emphasize that type 1 diabetes involves autoimmune destruction of pancreatic beta cells requiring therapeutic insulin management.",
        0
    ),
    (
        "Doctors hide miracle fruit from South American rainforest that kills all cancer cells in 24 hours",
        "Clickbait articles claim pharmaceutical companies suppressed a wild tropical berry whose extract dissolves malignant tumors instantly. Oncology researchers clarify that no single botanical compound cures systemic cancer overnight without targeted clinical efficacy.",
        0
    ),
    (
        "Injecting hydrogen peroxide into veins oxygenates bloodstream and replaces blood transfusions",
        "Alternative wellness posts promote intravenous hydrogen peroxide administration as a blood purification therapy. Medical toxicologists and the FDA warn intravenous hydrogen peroxide causes fatal gas embolism, intravascular hemolysis, and multi-organ failure.",
        0
    ),
    (
        "Chemotherapy causes 97 percent of cancer deaths and is designed by hospitals for profit",
        "Viral conspiracy graphics assert oncology chemotherapy treatments are deliberately lethal poisons marketed solely for hospital revenue. Peer-reviewed epidemiological registries demonstrate adjuvant chemotherapy has saved tens of millions of lives across curable cancers.",
        0
    )
]
