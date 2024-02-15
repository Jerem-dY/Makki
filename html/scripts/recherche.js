// En cours de réalisation : 

// Supprimer le contenu en cliquant sur corbeille 
function clearField(fieldId) {
    document.getElementById(fieldId).value = "";

}

// Afficher les résultats de la recherche filtrée
function search() {
    var mot = document.getElementById('search-mot').value;
    var pronunciation = document.getElementById('search-pronunciation').value;
    var etymology = document.getElementById('search-etymologie').value;
    var thematic = document.getElementById('search-thematique').value;
    var location = document.getElementById('search-lieu').value;
  
    // Logique de recherche
    let searchParams = new URLSearchParams();
  
    let searchMot = document.getElementById('search-mot').value.trim();
    if (searchMot !== '') {
      searchParams.append('search_mot', searchMot);
    }
  
    let searchPronunciation = document.getElementById('search-pronunciation').value.trim();
    if (searchPronunciation !== '') {
      searchParams.append('search_pronunciation', searchPronunciation);
    }
  
    let searchEtymologie = document.getElementById('search-etymologie').value.trim();
    if (searchEtymologie !== '') {
      searchParams.append('search_etymologie', searchEtymologie);
    }
  
    let searchThematique = document.getElementById('search-thematique').value;
    if (searchThematique !== '') {
      searchParams.append('search_thematique', searchThematique);
    }
  
    let searchLieu = document.getElementById('search-lieu').value;
    if (searchLieu !== '') {
      searchParams.append('search_lieu', searchLieu);
    }
  
    // URL de requête en fonction des valeurs des champs remplis
    // 'page_de_mots.php?' manquante
    let url = 'mots.php?' + searchParams.toString();
  
    // Requête AJAX à la page de mot avec l'URL construite
    let xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);
    xhr.onload = function() {
      if (xhr.status === 200) {
        // Affichez les résultats de la recherche dans la page actuelle
        let resultsDiv = document.getElementById('results');
        resultsDiv.innerHTML = xhr.responseText;
  
        // Ajoutez un gestionnaire d'événement de clic sur chaque lien de mot
        let motLinks = resultsDiv.getElementsByClassName('mot-link');
        for (let i = 0; i < motLinks.length; i++) {
          motLinks[i].addEventListener('click', function(event) {
            event.preventDefault(); // Empêche le comportement par défaut du lien
            let motId = this.dataset.motId; // Récupérez l'ID du mot à partir de l'attribut data-mot-id
            window.location.href = 'mots.php?id=' + motId; // Redirigez vers la page de mot choisi
          });
        }
      } else {
        console.log('Erreur de requête AJAX : ' + xhr.statusText);
      }
    };
    xhr.send();
  }

  