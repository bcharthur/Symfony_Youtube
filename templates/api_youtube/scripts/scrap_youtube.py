import os
import json
import yt_dlp
import shutil
import sys
from youtubesearchpython import VideosSearch

categories = {
    "Tendance": "tendance ytb france",
    "Musique": "tendance musique france",
    "Films et séries TV": "tendance film france",
    "Direct": "tendance direct france",
    "Jeux vidéo": "tendance jeux video france",
    "Actualités": "tendance actualite france",
    "Sport": "tendance sport france",
    "Savoirs & Cultures": "tendance savoir culture france",
    "Mode et beauté": "tendance mode beaute france",
    "Podcasts": "tendance podcasts france",
}

base_path = 'api/data'

# Supprimer le répertoire s'il existe
if os.path.exists(base_path):
    shutil.rmtree(base_path)

# Recréer les sous-répertoires nécessaires
for category in categories.keys():
    os.makedirs(f'{base_path}/{category}/media/video', exist_ok=True)
    os.makedirs(f'{base_path}/{category}/media/thumbnails', exist_ok=True)
    os.makedirs(f'{base_path}/{category}/media/title', exist_ok=True)

def progress_hook(d):
    if d['status'] == 'downloading':
        print(f"PROGRESS: {d['_percent_str']}")
    elif d['status'] == 'finished':
        print(f"PROGRESS: 100%")
    sys.stdout.flush()

def search_youtube(query, max_results=3):
    search = VideosSearch(query, limit=max_results, language='fr')
    results = search.result()['result']
    video_ids = []
    filtered_results = []

    for result in results:
        try:
            duration = result['duration'].split(':')
            if len(duration) == 2:
                total_seconds = int(duration[0]) * 60 + int(duration[1])
            elif len(duration) == 3:
                total_seconds = int(duration[0]) * 3600 + int(duration[1]) * 60 + int(duration[2])
            else:
                continue

            if total_seconds <= 1200:
                video_ids.append(result['id'])
                filtered_results.append(result)

            if len(video_ids) >= max_results:
                break
        except Exception as e:
            print(f"Error processing result {result['id']}: {e}", file=sys.stderr)

    return video_ids, filtered_results

def get_video_info(video_id, category):
    url = f"https://www.youtube.com/watch?v={video_id}"
    video_path = f'{base_path}/{category}/media/video/{video_id}.mp4'

    if os.path.exists(video_path):
        print(f"Video {video_id} already downloaded.")
        return None

    ydl_opts = {
        'format': 'best',
        'outtmpl': video_path,
        'writethumbnail': True,
        'skip_download': False,
        'noplaylist': True,
        'retries': 5,
        'fragment-retries': 5,
        'socket-timeout': 15,
        'progress_hooks': [progress_hook],  # Utilisation correcte de progress_hook
    }

    try:
        with yt_dlp.YoutubeDL(ydl_opts) as ydl:
            info_dict = ydl.extract_info(url, download=True)

            video_info = {
                "title": info_dict.get('title', None),
                "filename": f'{video_id}.mp4',
                "thumbnail_filename": None,
                "video_url": url,
                "video_path": video_path,
                "thumbnail_path": None,
            }

        thumbnail_extensions = ['jpg', 'webp', 'png']
        for ext in thumbnail_extensions:
            thumbnail_path = f'{base_path}/{category}/media/video/{video_id}.{ext}'
            if os.path.exists(thumbnail_path):
                new_thumbnail_path = f'{base_path}/{category}/media/thumbnails/{video_id}.{ext}'
                shutil.move(thumbnail_path, new_thumbnail_path)
                video_info['thumbnail_filename'] = f'{video_id}.{ext}'
                video_info['thumbnail_path'] = new_thumbnail_path
                break

        return video_info

    except yt_dlp.utils.DownloadError as e:
        print(f"Failed to download video {video_id}. Error: {e}", file=sys.stderr)
        if os.path.exists(video_path):
            os.remove(video_path)
        return None

    except Exception as e:
        print(f"Unexpected error occurred for video {video_id}. Error: {e}", file=sys.stderr)
        return None

def scrape_youtube_videos(query, category, max_results=3):
    video_ids, results = search_youtube(query, max_results)
    videos_data = []

    with open(f'{base_path}/{category}/media/title/titles.txt', 'w', encoding='utf-8') as title_file:
        for i, video_id in enumerate(video_ids):
            video_info = get_video_info(video_id, category)
            if video_info:
                video_info['id'] = video_id
                videos_data.append(video_info)
                title_file.write(f"{i + 1}. {video_info['title']}\n")

    return videos_data

sys.stdout = open(sys.stdout.fileno(), mode='w', encoding='utf8', buffering=1)

all_videos_data = {}
for category, query in categories.items():
    all_videos_data[category] = scrape_youtube_videos(query, category, max_results=3)

# Enregistrer les données JSON dans un fichier dans api/data
try:
    json_file_path = os.path.join(base_path, 'all_json_code.json')
    with open(json_file_path, 'w', encoding='utf-8') as f:
        json.dump(all_videos_data, f, indent=4, ensure_ascii=False)
    print(f"SUCCESS: Data has been written to {json_file_path}")
    sys.stdout.flush()
except (TypeError, ValueError) as e:
    print(f"ERROR: Error generating JSON output: {e}", file=sys.stderr)
    sys.stdout.flush()
    sys.exit(1)
