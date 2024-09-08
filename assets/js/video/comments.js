document.addEventListener('DOMContentLoaded', function() {
    const commentForm = document.getElementById('comment-form');
    const commentsList = document.getElementById('comments-list');
    const commentsCountElem = document.getElementById('comments-count');
    const commentLoading = document.getElementById('comment-loading');

    if (commentForm) {
        commentForm.addEventListener('submit', function(event) {
            event.preventDefault();
            if (commentForm.classList.contains('submitting')) {
                return; // Évite plusieurs soumissions
            }

            const formData = new FormData(commentForm);
            commentForm.classList.add('submitting'); // Empêche les soumissions multiples
            commentLoading.classList.remove('d-none');

            fetch(commentForm.action, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(response => response.json())
                .then(data => {
                    commentLoading.classList.add('d-none');
                    commentForm.classList.remove('submitting');

                    const newComment = document.createElement('div');
                    newComment.className = 'card mb-3';
                    newComment.innerHTML = `
                        <div class="card-body d-flex">
                            <img src="${data.profilePicture}" alt="User Avatar" class="rounded-circle me-3" style="width: 50px; height: 50px;">
                            <div>
                                <h6 class="mt-0">
                                    <a href="/profile/${data.userId}" class="text-decoration-none">${data.username}</a>
                                    <small class="text-muted">${data.createdAt}</small>
                                </h6>
                                <p>${data.content}</p>
                            </div>
                        </div>
                    `;
                    commentsList.prepend(newComment);
                    commentForm.reset();
                    commentsCountElem.textContent = parseInt(commentsCountElem.textContent) + 1;
                })
                .catch(error => {
                    commentLoading.classList.add('d-none');
                    commentForm.classList.remove('submitting');
                    console.error('Erreur lors de l\'ajout du commentaire :', error);
                });
        });
    }
});
