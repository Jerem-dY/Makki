from docx_to_json import ExtractData
from json_to_rdf import convert_to_rdf
import  argparse
import time
import os

VERBOSE = False

def echo(s: str):
    if VERBOSE:
        print(s)

if __name__ == "__main__":

    parser = argparse.ArgumentParser(
        prog='Extracteur Makki',
        description="Récupère les informations d'un fichier docx et en tire un fichier rdf (turtle).")

    parser.add_argument('filename', help="Chemin des fichiers docx d'entrée", nargs='+')
    parser.add_argument('--exclude', "-x", required=False, help="Chemin vers un fichier des tags à exclure (optionnel)", nargs=1, default="")
    parser.add_argument('--output', '-o', required=True, help="Dossier(s) vers lesquels exporter les données. Soit 1 seul, soit 1 par fichier d'entrée", nargs='+')
    parser.add_argument('--verbose', '-v', required=False, help="Affiche la progression du système.", action="store_true")
    parser.add_argument('--rdf', "-r", required=False, help="Stipule que les fichiers d'entrée sont déjà des fichiers json valides", action="store_true")

    args = parser.parse_args()

    if len(args.output) != 1:
        if len(args.output) != len(args.filename):
            raise argparse.ArgumentError("Il faut soit un seul dossier de sortie, soit un dossier par fichier d'entrée.")
        else:
            output_folder = args.output # several
    else:
        output_folder = [args.output[0]]*len(args.filename) # unique
    
    VERBOSE = args.verbose

    for f, d in zip(args.filename, output_folder):

        output_name = os.path.basename(f).split('.')[0]
        json_path = os.path.join(d, output_name + ".json")

        if not args.rdf:

            ed = ExtractData(f, args.exclude[0] if len(args.exclude) > 0 else "")

            echo(f"| Extraction du fichier `{f}`...")
            t0 = time.time()
            ed.extract_definition(VERBOSE)
            t1 = time.time()

            echo(f"\t> {t1-t0} s ({(t1-t0)/60} min)")

            ed.write_json_to_file(json_path)

        echo(f"\t> Export en RDF...")
        rdf = convert_to_rdf(json_path, output_name)

        with open(os.path.join(d, output_name + '.ttl'), mode="w", encoding="utf-8") as ttl:
            ttl.write(rdf)

        echo("\t> TERMINE")
    
    echo("TRAITEMENT TERMINE.")