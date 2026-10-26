from pathlib import Path
from PIL import Image, ImageChops, ImageOps

root = Path(__file__).resolve().parents[1]
source = root / "logo-assets" / "page-1-image-1-Im0.jpg"
output = root / "assets" / "images"
output.mkdir(parents=True, exist_ok=True)

image = Image.open(source).convert("RGB")
background = Image.new("RGB", image.size, "white")
difference = ImageChops.difference(image, background).convert("L")
mask = difference.point(lambda value: 255 if value > 18 else 0)
bounds = mask.getbbox()
if bounds is None:
    raise RuntimeError("No logo artwork found")

margin = 48
left = max(0, bounds[0] - margin)
top = max(0, bounds[1] - margin)
right = min(image.width, bounds[2] + margin)
bottom = min(image.height, bounds[3] + margin)
logo = image.crop((left, top, right, bottom))
logo.save(output / "logo.jpg", quality=88, optimize=True, progressive=True)

favicon = ImageOps.pad(logo, (128, 128), color="#FAF7F0", centering=(0.5, 0.5))
favicon.resize((64, 64), Image.Resampling.LANCZOS).save(output / "favicon.png", optimize=True)
print(f"Saved logo.jpg ({logo.width}x{logo.height}) and favicon.png")
