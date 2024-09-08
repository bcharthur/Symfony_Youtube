document.addEventListener('DOMContentLoaded', function() {
    const likeForm = document.getElementById('like-form');
    const likeButton = document.getElementById('like-button');
    const likesCountElem = document.getElementById('likes-count');
    const loadingSpinner = document.getElementById('like-loading');

    let isSubmitting = false;

    if (likeForm) {
        likeForm.addEventListener('submit', function(event) {
            event.preventDefault();
            if (isSubmitting || likeButton.disabled) {
                return;  // Évite les soumissions multiples
            }
            loadingSpinner.classList.remove('d-none');
            likeButton.disabled = true;
            isSubmitting = true; // Empêche la soumission multiple

            fetch(likeForm.action, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(response => response.json())
                .then(data => {
                    loadingSpinner.classList.add('d-none');
                    likeButton.disabled = false;
                    isSubmitting = false; // Réactivation après traitement

                    likesCountElem.textContent = data.likes_count;
                    likeButton.innerHTML = data.liked
                        ? `<i class="fa-solid fa-thumbs-up me-2"></i> ${data.likes_count}`
                        : `<i class="fa-regular fa-thumbs-up me-2"></i> ${data.likes_count}`;
                })
                .catch(error => {
                    loadingSpinner.classList.add('d-none');
                    likeButton.disabled = false;
                    isSubmitting = false;
                    console.error('Erreur lors du like/unlike :', error);
                    alert('Erreur lors du traitement de votre demande. Veuillez réessayer plus tard.');
                });
        });
    }
});
