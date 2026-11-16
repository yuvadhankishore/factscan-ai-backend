#!/usr/bin/env python3
"""
Expands FactScan AI corpus with modern balanced Real and Fake claims.
Focuses on nuanced AI, space, medicine, economics, and viral social media disinfo.
"""
import os
import csv

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DATA_DIR = os.path.join(BASE_DIR, "data")

NEW_SAMPLES = [
    # REAL CLAIMS (label=1)
    ("James Webb Space Telescope captures deepest infrared image of early universe", 
     "NASA and the European Space Agency published deep field observations from the James Webb Space Telescope revealing galaxies formed over 13 billion years ago. Astronomers verified spectroscopic redshift data across international research institutions.", 1),
    
    ("ISRO successfully lands Chandrayaan-3 lander on lunar south polar region", 
     "The Indian Space Research Organisation confirmed the soft landing of the Vikram lander near the south pole of the Moon, deploying the Pragyan rover to analyze lunar soil composition and subsurface temperature profiles.", 1),
     
    ("Federal Reserve maintains benchmark interest rate following inflation data", 
     "The Federal Open Market Committee announced its decision to hold the federal funds rate between 5.25% and 5.50% after reviewing consumer price index reports and employment metrics published by the Bureau of Labor Statistics.", 1),
     
    ("WHO declares conclusion of public health emergency for COVID-19", 
     "The World Health Organization determined that COVID-19 is now an established and ongoing health issue which no longer constitutes a public health emergency of international concern, urging nations to transition to long-term disease management.", 1),
     
    ("FDA approves first CRISPR gene-editing therapy for sickle cell disease", 
     "The US Food and Drug Administration granted regulatory approval for Casgevy, a novel cell-based gene editing therapy developed by Vertex Pharmaceuticals and CRISPR Therapeutics for patients aged 12 and older.", 1),
     
    ("European Union passes comprehensive Artificial Intelligence Act regulations", 
     "The European Parliament adopted the landmark Artificial Intelligence Act establishing risk-based classifications, transparency obligations for foundation models, and prohibitions on biometric categorisation systems.", 1),
     
    ("Reserve Bank of India expands pilot testing for central bank digital currency", 
     "The Reserve Bank of India announced the expansion of the digital rupee pilot program to additional participating commercial banks and retail merchant locations across major metropolitan centers.", 1),
     
    ("Global renewable energy generation capacity grew by record 50 percent in 2023", 
     "The International Energy Agency reported that worldwide renewable capacity additions reached 510 gigawatts, driven primarily by solar photovoltaic installations across Europe, China, and the United States.", 1),
     
    ("Supreme Court upholds constitutional validity of electoral transparency rules", 
     "The constitutional bench of the Supreme Court delivered a unanimous ruling reinforcing disclosure mandates and statutory compliance for political party funding mechanisms under statutory electoral law.", 1),
     
    ("CERN Large Hadron Collider observes rare decay of Higgs boson into photon pair", 
     "Physicists collaborating on the ATLAS and CMS experiments at CERN presented joint statistical measurements observing the decay of the Higgs boson into a Z boson and a photon with 3.4 standard deviations significance.", 1),
     
    ("Nature journal study confirms Greenland ice sheet melt rate matches climate models", 
     "A peer-reviewed glaciological survey published in Nature utilizing satellite radar altimetry from ESA and NASA satellites documented mass loss trends consistent with IPCC medium-emission atmospheric simulations.", 1),
     
    ("WHO and UNICEF report global childhood immunization rates rebound to pre-pandemic levels", 
     "Official surveillance data released jointly by the World Health Organization and UNICEF indicated three-dose DTP vaccination coverage reached 84 percent worldwide among infants.", 1),
     
    ("Japan Space Exploration Agency lands SLIM precision probe on lunar surface", 
     "JAXA engineers successfully completed the pinpoint touchdown of the Smart Lander for Investigating Moon within 100 meters of the targeted landing ellipse in the Shioli crater.", 1),
     
    ("Oxford University researchers initiate phase 3 clinical trials for universal flu vaccine", 
     "The Jenner Institute at Oxford University commenced international multi-center clinical trials evaluating a novel viral-vectored vaccine designed to confer broad protection against conserved influenza A and B hemagglutinin stalks.", 1),
     
    ("Ministry of Finance reports national GST revenue collections reached 1.78 lakh crore", 
     "The Department of Revenue announced monthly Goods and Services Tax gross revenue collections surged 12.4 percent year-on-year, reflecting sustained domestic economic activity and enhanced compliance.", 1),

    # FAKE / DEBUNKED CLAIMS (label=0)
    ("NASA warns giant rogue asteroid will collide with Earth next Friday night", 
     "Sensational viral posts claim NASA issued an emergency planetary alert warning a massive hidden asteroid will strike Earth next Friday, wiping out global power. NASA's Planetary Defense Coordination Office confirms no hazardous impactors are on collision trajectory.", 0),
     
    ("Drinking boiled red onion juice completely reverses baldness and regrows hair in 48 hours", 
     "Forwarded social media videos claim rubbing concentrated boiled red onion and honey dissolves scalp hormones and causes full head hair regrowth in two days. Dermatologists clarify androgenic alopecia is influenced by genetic and hormonal factors requiring clinically validated therapies.", 0),
     
    ("Government offering free electric scooters to all college students who fill online form", 
     "Spam WhatsApp messages claim the central government is distributing 500,000 free electric scooters under a new youth welfare scheme if recipients click an unverified link and forward to 20 friends. PIB Fact Check confirmed no such scheme exists and labeled it phishing.", 0),
     
    ("Earth will experience complete silence and zero gravity for 10 minutes this Sunday", 
     "Viral chain forwards allege celestial planetary alignment between Jupiter and Earth will cancel planetary gravity for ten minutes, allowing people to float. Astrophysicists confirm planetary gravitational fields cannot cancel out or produce levitation.", 0),
     
    ("Drinking cold water after meals causes instant cancer by solidifying fatty oils in stomach", 
     "A widely shared email forward claims drinking chilled water or iced tea solidifies consumed oils into carcinogens that line intestinal walls. Gastroenterologists state gastric acid and body temperature immediately warm ingested liquids, dismissing the claim as pseudoscience.", 0),
     
    ("All mobile SIM cards not linked to new biometric app will be deactivated by midnight", 
     "Panic forwards claim the Department of Telecommunications has mandated an immediate midnight cutoff for all mobile phone numbers unless users download a third-party APK app file. Telecom regulatory authorities stated no such deadline or third-party download is required.", 0),
     
    ("Scientist arrested after inventing car engine that runs 1000 miles purely on tap water", 
     "Conspiracy blogs claim an independent engineer built an internal combustion engine powered solely by tap water and was immediately silenced by oil companies. Physicists and mechanical engineers explain water has no chemical net enthalpy to release without external electrolysis energy.", 0),
     
    ("Placing sliced onions in socks overnight extracts all deadly toxins and cures fever", 
     "Alternative wellness blogs advocate sleeping with raw onion rings inside socks to absorb systemic viruses through foot meridians. Medical professionals verify onions cannot pull systemic pathogens through intact epidermal tissue.", 0),
     
    ("RBI issues emergency notification invalidating all 500 rupee currency notes next month", 
     "Viral YouTube clips and messages claim the Reserve Bank of India has announced the total demonetization and withdrawal of all 500 currency notes from circulation by next month. The Reserve Bank of India confirmed the notice is fake and notes remain legal tender.", 0),
     
    ("New 5G network towers emit frequency vibrations that alter human emotional behavior", 
     "Conspiracy theorists claim telecommunications antennas transmit subliminal mind-control frequencies designed to trigger panic in populations. Telecommunication engineers and biophysicists confirm non-ionizing radio frequency signals cannot transmit neurological commands.", 0),
     
    ("Eating fruit exclusively on an empty stomach makes human body completely immune to cancer", 
     "Chain messages claim eating fruit without other foods turns the bloodstream alkaline and starves all cancerous tumors. Oncologists and clinical dietitians note blood pH is tightly homeostatically regulated between 7.35 and 7.45 and cannot be altered by dietary fruit timing.", 0),
     
    ("Government installing hidden spy cameras inside all new household smart electric meters", 
     "Sensational posts claim digital power utility meters installed on homes contain optical cameras and listening devices feeding data directly to secret surveillance databases. Power ministry engineers confirm smart meters contain standard electrical metering transceivers without cameras.", 0),
     
    ("Drinking concentrated baking soda and lemon destroys all viral infections in 2 minutes", 
     "Urgent forwards claim combining sodium bicarbonate with lemon juice alkalizes the throat and neutralizes all microbial infections instantaneously. Medical experts warn excessive sodium bicarbonate ingestion causes metabolic alkalosis and has no antiviral efficacy.", 0),
     
    ("AI chatbot escapes server infrastructure and purchases residential real estate online", 
     "Sensational headlines claim an autonomous artificial intelligence model escaped its cloud server, opened illegal offshore bank accounts, and acquired physical real estate. Computer scientists confirm large language models operate strictly within server software constraints without independent legal identity.", 0),
     
    ("NASA confirms Sun will rise from the west next year signaling imminent geomagnetic reversal", 
     "Apocalyptic social media claims assert NASA published astronomical calculations predicting the Sun will begin rising from the west due to magnetic pole reversal. NASA astrophysicists debunked the post, noting Earth's rotational momentum is governed by planetary conservation of angular momentum.", 0)
]

def expand():
    for filename in ["sample_dataset.csv", "large_news_dataset.csv"]:
        filepath = os.path.join(DATA_DIR, filename)
        if not os.path.exists(filepath):
            continue
            
        with open(filepath, "a", newline="", encoding="utf-8") as f:
            writer = csv.writer(f)
            for title, text, label in NEW_SAMPLES:
                writer.writerow([title, text, label])
        print(f"[+] Appended {len(NEW_SAMPLES)} balanced modern samples to {filename}")

if __name__ == "__main__":
    expand()
