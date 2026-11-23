<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/bootstrap.php';
seed_test_catalog();
$videoPath = $argv[1] ?? '';
if (!GalleryMedia::isVideo($videoPath) || !is_file(APP_ROOT . '/' . $videoPath)) { throw new InvalidArgumentException('Supply the path to an isolated MP4 fixture.'); }
$video = ['path'=>$videoPath,'caption'=>'A quiet moment','alt_text'=>'A short test video'];
echo '<!doctype html><meta charset="utf-8"><base href="' . escape('file:///' . str_replace('\\','/',realpath(APP_ROOT)) . '/') . '"><title>Hero media browser test</title>';
echo '<script>window.heroTimers=new Map(); let heroTimerId=0; window.setInterval=(fn,delay)=>{heroTimers.set(++heroTimerId,fn);return heroTimerId;};window.clearInterval=id=>heroTimers.delete(id);</script>';
foreach ([[Gallery::defaults()[0],$video,Gallery::defaults()[1]],[$video]] as $media) {
    echo str_replace('<script src="assets/js/hero-carousel.js" defer></script>', '', render_template_for_test('home', ['heroImages'=>$media,'services'=>[],'settings'=>[]]));
}
echo '<script>' . file_get_contents(APP_ROOT . '/assets/js/hero-carousel.js') . '</script>';
?>
<pre id="test-result">RUNNING</pre>
<script>
(async () => {
  const check = (condition, message) => { if (!condition) throw new Error(message); };
  try {
    const [mixed,single] = document.querySelectorAll('[data-hero-carousel]');
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const video = mixed.querySelector('video');
    check(single.querySelector('video').controls && single.querySelector('video').loop, 'single video keeps native controls and loops');
    check(heroTimers.size === (reduced ? 0 : 1), 'photo rotation respects reduced motion');
    mixed.querySelector('[data-hero-next]').click();
    check(mixed.querySelector('.is-active video') === video, 'next selects video slide');
    check(video.muted && video.playsInline && video.controls, 'hero video muted, inline and controllable');
    check(heroTimers.size === 0, 'slideshow does not cut off a video after six seconds');
    if (reduced) {
      check(video.paused && single.querySelector('video').paused, 'reduced motion prevents automatic video playback');
    } else {
      await video.play();
      check(!video.paused && video.readyState >= 2, 'real H.264 fixture decodes and plays');
      mixed.querySelector('[data-hero-pause]').click();
      check(video.paused, 'pause slideshow pauses current video');
      mixed.querySelector('[data-hero-pause]').click();
      video.dispatchEvent(new Event('ended'));
      check(!mixed.querySelector('.is-active video'), 'video completion advances to photo');
      check(video.paused, 'inactive video is paused');
    }
    mixed.querySelector('[data-hero-next]').click();
    check(video.paused && video.tabIndex === -1 && video.closest('[data-hero-slide]').inert, 'hidden video cannot play or receive keyboard focus');
    document.querySelector('#test-result').textContent = 'PASS: mixed media, real playback, full video duration, pause, inactive slides and reduced motion (' + reduced + ')';
  } catch (error) { document.querySelector('#test-result').textContent = 'FAIL: ' + error.message; }
  finally { document.querySelectorAll('video').forEach(video => video.pause()); }
})();
</script>
