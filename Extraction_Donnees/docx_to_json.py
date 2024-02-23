import json
from docx import Document
from rdf_id import data_to_uuid
import re 



class ExtractData:

    prefixes_fr = {'[Imper.]', '[Jeux]', '[Alim.]', '[Bot.]', '[Zool.]', '[Zool. ; ois.]', '[Zool./Mar. ; poiss.]', '[Zool. ; insec.]', '[Méd./Mal.]', '[Mus.]', '[Mus. ; instr.]', '[Loc. ; vulg.]', '[Pop. ; vulg.]', '[Vulg.]', '[Compar. ; vulg.]'}
    prefixes_ar = {'[لِلأمر]', '[ألعاب]', '[غِذاء]', '[نَبات]', '[حَيَوان]', '[حَيَوان؛ طُيُور]', '[حَيَوانٌ بَـحرِيّ؛ أسـماك]', '[حَيَوان؛ حَشَرات]', '[طِبّ]', '[مُوسِيقى]', '[مُوسِيقى؛ آلَة]', '[عِبارَة؛ سُوقِيّ]', '[شَعبِيّ؛ سُوقِيّ]', '[سُوقِيّ]', '[لِلـمُقارَنَة؛ سُوقِيّ]'}

    cols_lang = ["ar", "ar", "fr", "ar"]

    regex_coverage = re.compile(r"(\((?P<def>[\d])\) |)((?P<first_loc>[\w ]+), |)(((?P<loc1>[\w]+)( (\((?P<prec1>.+)\))|) (&|et) (?P<loc2>[\w]+)( \((?P<prec2>.+)\)|))|(?P<loc_glob>[\w ]+) (\((?P<loc>[\w ]+)\))|(?P<loca>[\w ]+[\w]+)( (\((?P<prec3>.+)\))|))( : (?P<precision>.+[^\n ]{1})|)", re.M | re.U)
    regex_definition = re.compile(r"^(?P<head>(?P<nb>[0-9]\.) |(\((?P<symbol>.{1})\) )| ?® ?|)((?P<tag>\[.+\]) |)(- (?P<example>.+?)|(?P<def>.+?)) *$", re.M | re.U)

    def __init__(self, file_path, annotations_file_path):
        self.file_path = file_path
        self.annotations_file_path = annotations_file_path
        self.data = {}

    def generate_term_uri(self, description_id):
        return str(data_to_uuid(description_id))

    def make_term(self, terme, description_fr, description_ar):

        data = {}

        # On rassemble les informations des descriptions en français et en arabe
        for key in description_fr.keys() | description_ar.keys():

            if key in description_fr and key in description_ar:
                data[key] = description_fr[key] + description_ar[key]
            elif key in description_fr:
                data[key] = description_fr[key]
            else:
                data[key] = description_ar[key]

        data["title"] = terme

        return data

    def parse_term(self, s: str):

        # On découpe la case du mot en séparant avec les tirets
        s = [el.strip() for el in s.split('-')]

        # On isole le mot lui-même de ses exemples
        #NOTE: on perd actuellement les informations de pluriel, de féminin, etc.
        return re.match(r"[^\d\(\)\n\r\f]+", s[0]).group(0).strip(), [(ex, "ar") for ex in s[1:]]
    
    def parse_coverage(self, s: str, lang: str):

        output = []
        s = s.lower()
        # On utilise la regex `regex_coverage` pour découper la chaîne en différentes informations
        for m in self.regex_coverage.finditer(s.strip()):

            prec = m['precision']

            if m['first_loc']:
                output.append((m['first_loc'], lang, prec))

            elif m['loc1']:
                output.append((m['loc1'].lower(), lang, m['prec1'] if m['prec1'] else prec))
                output.append((m['loc2'].lower(), lang, m['prec2'] if m['prec2'] else prec))
            
            elif m['loc_glob']:
                output.append((m['loc_glob'].lower(), lang, prec))
                output.append((m['loc'].lower(), lang, prec))
            
            elif m['loca']:
                output.append((m['loca'].lower(), lang, m['prec3'] if m['prec3'] else prec))
        
        return output


    def parse_definition(self, s: str, tags: set, lang: str):

        output = {"abstract" : [""]}

        # On utilise la regex `regex_definition` pour découper la définition en éléments reconnus
        for d in self.regex_definition.finditer(s):

            d = d.groupdict()
            
            # On traite chaque cas possible
            if d['symbol'] == '*':
                output['abstract'][0] += '\n(*)' + d['def'].strip()
                        
            # TODO: traiter '(+)'
            elif d['symbol'] == '+':
                if 'related' not in output:
                    output['related'] = []
                output['related'].append((d['def'].strip(), lang))

            
            elif d['symbol'] == 'ε':

                if 'etymo' not in output:
                    output['etymo'] = []

                output['etymo'].append((d['def'].strip(), lang))

            elif d['symbol'] == 'π':

                if 'pron' not in output:
                    output['pron'] = []

                output['pron'].append((d['def'].strip(), lang))

            elif d['head'].strip() == "®":

                if lang == "fr":
                    if 'coverage' not in output:
                        output['coverage'] = []

                    output['coverage'] += self.parse_coverage(d['def'], lang)
                else:
                    output['abstract'][0] += '\n' + d['def'].strip()
            
            elif not d['symbol']:
                if d['def']:

                    if d['tag'] and d['tag'] != '':
                    
                        tg = d['tag'].split('/')[0].split(';')[0].strip().rstrip(']')

                        if not (tg in tags):

                            if 'subject' not in output:
                                output['subject'] = []

                            output['subject'].append((tg[1:].strip(), lang))
                        else:
                            output['abstract'][0] += d['tag'] + ' '

                    output['abstract'][0] += d['def']

                elif d['example']:
                    if 'example' not in output:
                            output['example'] = []

                    output['example'].append((d['example'].strip(), lang))


        output['abstract'][0] = (output['abstract'][0], lang)

        return output

    def parse_row(self, row, tags):

        # On itère sur les lignes qui ont un mot de présent dans la première colonne
        if row.cells[0].text.strip() != "":

            # On récupère le terme et les examples
            terme, examples = self.parse_term(row.cells[0].text)

            # On récupère chaque description et les tags valides associés (= pas dans annotations.txt)
            description_fr = self.parse_definition(row.cells[2].text, tags, "fr")
            description_ar = self.parse_definition(row.cells[1].text, tags, "ar")

            # On ajoute le terme au dictionnaire ainsi que les définitions associées
            term_uri = self.generate_term_uri(description_fr['abstract'][0][0])

            if not terme in self.data:
                self.data[terme] = {"description" : {}}

            self.data[terme]["description"][term_uri] = self.make_term(terme, description_fr, description_ar)
            

            # On ajoute les exemples
            if len(examples) > 0:
                if not 'example' in self.data[terme]["description"][term_uri]:
                    self.data[terme]["description"][term_uri]['example'] = []

                self.data[terme]["description"][term_uri]["example"] += examples
            
            # On ajoute les synonymes en leur donnant la même définition que le mot actuel
            if (alternatif := re.match(r"(\([0-9]\)|) ?← (.*)", row.cells[3].text)) != None:
                
                for alt in alternatif.group(2).split("،"):
                    alt = alt.strip()
                    if alt != "":
                        if not alt in self.data:
                            self.data[alt] = {"description" : {}}

                        self.data[alt]["description"][term_uri] = self.make_term(alt, description_fr, description_ar)


    def extract_definition(self, verbose=True):

        # On récupère le fichier de données et l'annexe des tags à éliminer
        document = Document(self.file_path)
        tags, symbols = self.read_annotations_file() 
        # TODO: prendre en compte les symboles
        # On itère sur les tableaux du document
        for i, table in enumerate(document.tables):
            # Si l'on a suffisamment de colonnes, on itère sur les lignes
            if len(table.columns) >= 4:
                for i, row in enumerate(table.rows):
                    if verbose:
                        print(f"\t\t| {i+1:05} / {len(table.rows):05} |")
                    self.parse_row(row, tags)

    def read_annotations_file(self):

        tags = []
        symbols = []

        try:
            with open(self.annotations_file_path, mode="r", encoding="utf-8") as file:
                annotations_to_delete = file.readlines()

                for annotation in annotations_to_delete:
                    if not (annotation[0] in {'[', '('}):
                        symbols.append(annotation.strip())
                    else:
                        tags.append(annotation.strip())
        except:
            print("Fichier d'exclusion INVALIDE :", self.annotations_file_path)
            pass

        return (set(tags), set(symbols))

    def write_json_to_file(self, file_path):
        with open(file_path, mode="w", encoding="utf-8") as file:
            json.dump(self.data, file, ensure_ascii=False, indent=4)
