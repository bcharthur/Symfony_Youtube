const fs = require('fs');
const path = require('path');
const ytdl = require('ytdl-core');
const axios = require('axios');
const cheerio = require('cheerio');

// Liste des catégories et des requêtes associées
const categories = {
    "Tendance": "tendance ytb france",
    "Musique": "tendance musique france",
    "Films et séries TV": "tendance film france",
    "Direct": "tendance direct france",
    "Jeux vidéo": "tendance jeux video france",
    "Actualités": "tendance actualite france",
    "Sport": "tendance sport france",
    "Savoirs & Cultures": "tendance savoir culture france",
    "Mode et beauté": "tendance mode beaute france",
    "Podcasts": "tendance podcasts france"
};

// Création des dossiers pour chaque catégorie
Object.keys(categories).forEach(category => {
    const mediaPath = path.join(__dirname, '../../../public/media', category, 'media');
    fs.mkdirSync(path.join(mediaPath, 'video'), { recursive: true });
    fs.mkdirSync(path.join(mediaPath, 'thumbnails'), { recursive: true });
    fs.mkdirSync(path.join(mediaPath, 'title'), { recursive: true });
});

async function searchYoutube(query, maxResults = 3) {
    const url = `https://www.youtube.com/results?search_query=${encodeURIComponent(query)}`;
    const response = await axios.get(url);
    const $ = cheerio.load(response.data);

    const results = [];
    $('a#video-title').each((i, element) => {
        if (i < maxResults) {
            const videoId = $(element).attr('href').split('v=')[1];
            const title = $(element).attr('title');
            const duration = $(element).parent().find('.ytd-thumbnail-overlay-time-status-renderer').text().trim();
            results.push({ videoId, title, duration });
        }
    });

    return results.filter(result => {
        const durationParts = result.duration.split(':');
        const totalSeconds = durationParts.reduce((acc, time) => (60 * acc) + +time);
        return totalSeconds <= 1200;  // 1200 seconds = 20 minutes
    });
}

async function getVideoInfo(videoId, category) {
    const videoUrl = `https://www.youtube.com/watch?v=${videoId}`;
    const videoPath = path.join(__dirname, '../../../public/media', category, 'media', 'video', `${videoId}.mp4`);
    const thumbnailPath = path.join(__dirname, '../../../public/media', category, 'media', 'thumbnails', `${videoId}.jpg`);

    // Télécharger la vidéo
    await new Promise((resolve, reject) => {
        ytdl(videoUrl, { quality: 'highest' })
            .pipe(fs.createWriteStream(videoPath))
            .on('finish', resolve)
            .on('error', reject);
    });

    // Télécharger la miniature
    const info = await ytdl.getInfo(videoUrl);
    const thumbnailUrl = info.videoDetails.thumbnails[info.videoDetails.thumbnails.length - 1].url;
    const thumbnail = await axios({
        url: thumbnailUrl,
        responseType: 'stream',
    });
    thumbnail.data.pipe(fs.createWriteStream(thumbnailPath));

    return {
        title: info.videoDetails.title,
        thumbnail_url: thumbnailUrl,
        video_url: videoUrl
    };
}

async function scrapeYoutubeVideos(query, category, maxResults = 3) {
    const videos = await searchYoutube(query, maxResults);
    const videoData = [];

    const titleFilePath = path.join(__dirname, '../../../public/media', category, 'media', 'title', 'titles.txt');
    const titleFile = fs.createWriteStream(titleFilePath, { flags: 'w' });

    for (const [i, video] of videos.entries()) {
        const videoInfo = await getVideoInfo(video.videoId, category);
        videoData.push({ ...videoInfo, id: video.videoId });

        // Écrire le titre dans le fichier texte
        titleFile.write(`${i + 1}. ${videoInfo.title}\n`);
    }

    titleFile.close();

    // Sauvegarde des informations des vidéos dans un fichier JSON
    const jsonFilePath = path.join(__dirname, '../../../public/media', category, 'media', 'video_data.json');
    fs.writeFileSync(jsonFilePath, JSON.stringify(videoData, null, 4), 'utf-8');
}

// Exécution du scraping pour chaque catégorie
(async () => {
    for (const [category, query] of Object.entries(categories)) {
        console.log(`Téléchargement des vidéos pour la catégorie: ${category}`);
        await scrapeYoutubeVideos(query, category, 3);
    }
})();
