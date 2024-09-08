console.log('Other_videos script loaded');
document.addEventListener('DOMContentLoaded', function() {
    const loadMoreBtn = document.getElementById('load-more-btn');
    const otherVideosList = document.getElementById('other-videos-list');

    loadMoreBtn.addEventListener('click', function() {
        const offset = loadMoreBtn.getAttribute('data-offset');
        const limit = loadMoreBtn.getAttribute('data-limit');
        const videoId = loadMoreBtn.getAttribute('data-video-id'); // Ajoutez cette ligne

        fetch(`/video/${videoId}/load-more?offset=${offset}&limit=${limit}`, {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(response => response.json())
            .then(data => {
                data.videos.forEach(video => {
                    const videoItem = document.createElement('a');
                    videoItem.href = `/video/${video.id}`;
                    videoItem.className = 'list-group-item list-group-item-action';
                    videoItem.innerHTML = `
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1">${video.title}</h6>
                        <small>Ajouté le ${video.createdAt}</small>
                    </div>
                `;
                    otherVideosList.appendChild(videoItem);
                });

                if (data.videos.length < limit) {
                    loadMoreBtn.remove();
                } else {
                    loadMoreBtn.setAttribute('data-offset', parseInt(offset) + parseInt(limit));
                }
            })
            .catch(error => console.error('Erreur lors du chargement des vidéos :', error));
    });
});
