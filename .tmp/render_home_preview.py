from pathlib import Path
from urllib.request import urlopen

root = Path(__file__).resolve().parents[1]
url = "http://localhost/www.happymobilespa.com/"
with urlopen(url, timeout=20) as response:
    html = response.read().decode("utf-8")
preview = html.replace(
    "<head>",
    '<head><base href="' + url + '">',
    1,
).replace(
    "</head>",
    '<style>html{scroll-behavior:auto!important}body{animation:none!important}</style></head>',
    1,
)
(root / ".tmp" / "home-preview.html").write_text(preview, encoding="utf-8")
sections = (
    ("approach", ".intro"),
    ("experience", "#experience"),
    ("treatments", "#treatments"),
    ("team", "#team"),
    ("gallery-preview", "#gallery-preview"),
    ("faqs", "#faqs"),
)
for section_name, selector in sections:
    section_css = (
        "<style>.site-header,.site-footer,main>section:not(" + selector + "),"
        ".mobile-book-bar{display:none!important}</style></head>"
    )
    section_preview = preview.replace("</head>", section_css, 1)
    (root / ".tmp" / ("home-" + section_name + "-preview.html")).write_text(
        section_preview, encoding="utf-8"
    )

