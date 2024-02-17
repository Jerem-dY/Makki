from docx_to_json import ExtractData
import time

ed = ExtractData(r"C:\Users\Jérémy Bourdillat\OneDrive\Documents\VIE ETUDIANTE\2023-2024\Cours\Projet Pro\extracteur\Alif_revu_YZ.docx", "annotations.txt")

t0 = time.time()
ed.extract_definition()
t1 = time.time()

print(f"{t1-t0} s")

ed.write_json_to_file("Alif_revu_YZ.json")