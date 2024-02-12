import json
import os 

def convert_to_rdf(input_json, source_file_name):
    rdf_prefixes = """    @prefix rdf:   <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
    @prefix rdfs:  <http://www.w3.org/2000/01/rdf-schema#> .
    @prefix xsd:   <http://www.w3.org/2001/XMLSchema#> .
    @prefix dc:    <http://purl.org/dc/terms/> .
    @prefix lex:   <lexique/> .

    """

    rdf_template = """<lexique/{title}#{identifier}>
        dc:creator "{creator}" ;
        dc:publisher "{publisher}" ;
        dc:identifier "{identifier}" ;
        dc:source "{source}" ;
        dc:title "{title}"@ar ;
        {coverage}
        {example}
        {etymo}
        {subject}
        {abstract} .
        """

    with open(input_json, 'r', encoding='utf-8') as json_file:
        data = json.load(json_file)

    source_file_name = os.path.basename(source_file_name)

    rdf_output = rdf_prefixes + '\n'.join([
        rdf_template.format(

            title=title,

            identifier=lexeme_id,

            creator="Hassan Makki",

            publisher="Editions Geuthner",

            coverage=';\n'.join([f'dc:coverage "{subj[0]}"@fr' for subj in lexeme_info.get("coverage", [])]) + ' ;' if lexeme_info.get("coverage") else "",

            subject=";\n".join([f'dc:subject "{subject[0]}"@{subject[1]}' for subject in lexeme_info.get("subject", [])]) + ' ;' if lexeme_info.get("subject") else "",

            example=";\n".join([f'dc:example \"\"\"{example[0]}\"\"\"@{example[1]}' for example in lexeme_info.get("example", [])]) + ' ;' if lexeme_info.get("example") else "",

            source= source_file_name,

            etymo=";\n".join([f'lex:etymo "{etymo[0]}"@{etymo[1]}' for etymo in lexeme_info.get("etymo", [])])+ ' ;' if lexeme_info.get("etymo") else "",

            abstract=";\n".join([f'dc:abstract \"\"\"{abstract[0]}\"\"\"@{abstract[1]}' for abstract in lexeme_info.get("abstract", [])]) if lexeme_info.get("abstract") else ""
        )

        for title, lexeme_data in data.items()
        for lexeme_id, lexeme_info in lexeme_data["description"].items()
    ])

    return rdf_output


