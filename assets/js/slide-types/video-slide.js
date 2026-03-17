/**
 * Video/Audio Slide Renderer
 * Tracks watch percentage; marks complete when threshold is reached.
 */
'use strict';

const VideoSlide = (() => {

    function render(container, slide, onComplete) {
        const c = slide.content || {};
        const threshold = c.completion_threshold ?? 0.8;
        const isAudioOnly = c.audio_only === true;
        let completed = false;
        let startTime = null;

        const infoText = window.LANG?.video_completion_info || 'Please watch the video completely.';

        // Build player HTML
        const mediaEl = isAudioOnly
            ? buildAudio(c)
            : buildVideo(c);

        container.innerHTML = `
            <div class="video-slide">
                <h1 class="slide-title">${escapeHtml(slide.title)}</h1>
                <div class="video-slide__player">${mediaEl}</div>
                <p class="video-slide__info">${infoText}</p>
                <div class="video-slide__progress">
                    <div class="video-slide__progress-fill" id="video-watch-bar" style="width:0%"></div>
                </div>
            </div>`;

        const media = container.querySelector('video, audio');
        if (!media) return;

        media.addEventListener('play', () => {
            if (!startTime) startTime = Date.now();
        });

        media.addEventListener('timeupdate', () => {
            if (!media.duration || media.duration === 0) return;
            const pct = media.currentTime / media.duration;
            const bar = container.querySelector('#video-watch-bar');
            if (bar) bar.style.width = (pct * 100).toFixed(1) + '%';

            if (!completed && pct >= threshold) {
                completed = true;
                const watchedSeconds = startTime ? Math.round((Date.now() - startTime) / 1000) : 0;
                onComplete(slide.id, {
                    status: 'completed',
                    watched_pct: pct
                });
                container.querySelector('.video-slide__info')?.remove();
            }
        });

        media.addEventListener('ended', () => {
            if (!completed) {
                completed = true;
                onComplete(slide.id, { status: 'completed', watched_pct: 1.0 });
            }
        });
    }

    function buildVideo(c) {
        const src = c.src || '';
        const poster = c.poster ? `poster="${c.poster}"` : '';
        const caption = c.caption_file
            ? `<track kind="subtitles" src="${c.caption_file}" srclang="de" label="Deutsch">`
            : '';
        return `<video controls ${poster} preload="metadata" style="max-width:100%;max-height:70vh">
            <source src="${src}" type="video/mp4">
            ${caption}
            <p>${window.LANG?.video_error || 'Das Video konnte nicht geladen werden.'}</p>
        </video>`;
    }

    function buildAudio(c) {
        const src = c.src || '';
        return `<audio controls preload="metadata" style="width:100%">
            <source src="${src}" type="audio/mpeg">
            <source src="${src}" type="audio/ogg">
        </audio>`;
    }

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    return { render };
})();
