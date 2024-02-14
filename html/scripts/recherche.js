// En cours de réalisation : 
// Supprimer le contenu en cliquant sur corbeille 
function clearField(fieldId) {
    document.getElementById(fieldId).value = "";

}

//  Afficher les résultats de la recherche filtrée
function search() {
    var searchQuery = document.getElementById('search-mot').value;
    var pronunciation = document.getElementById('search-pronunciation').value;
    var etymology = document.getElementById('search-etymologie').value;
    var thematic = document.getElementById('search-thematique').value;
    var location = document.getElementById('search-lieu').value;

    // Logique de recherche
}