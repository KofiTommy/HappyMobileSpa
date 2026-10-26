from pathlib import Path
from pypdf import PdfReader

reader = PdfReader("Happy Mobile Massage Logo.pdf")
output = Path("logo-assets")
output.mkdir(exist_ok=True)
for page_number, page in enumerate(reader.pages, start=1):
    for image_number, image in enumerate(page.images, start=1):
        target = output / f"page-{page_number}-image-{image_number}-{image.name}"
        target.write_bytes(image.data)
        print(target)
