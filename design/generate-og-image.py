"""
Regenerates public/assets/og-image.png (1200x630 Open Graph / social share
image) from the existing face icon + brand colors in style.css.

Run from anywhere: python3 design/generate-og-image.py
Requires Pillow (pip install Pillow) and macOS system fonts (Arial, Arial Bold).
"""

import os
from PIL import Image, ImageDraw, ImageFont

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

W, H = 1200, 630
BG = (249, 249, 249)      # --color-white
BLACK = (18, 18, 18)       # --color-black
FAINT = (168, 168, 168)    # --color-faint

canvas = Image.new("RGB", (W, H), BG)
draw = ImageDraw.Draw(canvas)

icon = Image.open(os.path.join(ROOT, "public/favicon/web-app-manifest-512x512.png")).convert("RGBA")
icon_size = 200
icon = icon.resize((icon_size, icon_size), Image.LANCZOS)

gap = 50
font_bold = ImageFont.truetype("/System/Library/Fonts/Supplemental/Arial Bold.ttf", 170)
text = "meh"
text_bbox = draw.textbbox((0, 0), text, font=font_bold)
text_w = text_bbox[2] - text_bbox[0]
text_h = text_bbox[3] - text_bbox[1]

lockup_w = icon_size + gap + text_w
lockup_h = max(icon_size, text_h)

start_x = (W - lockup_w) // 2
icon_y = H // 2 - 40 - icon_size // 2
canvas.paste(icon, (start_x, icon_y), icon)

text_x = start_x + icon_size + gap
text_y = H // 2 - 40 - text_bbox[1] - text_h // 2
draw.text((text_x, text_y), text, font=font_bold, fill=BLACK)

tagline = "weil auch mittelmäßige Meinungen eine Bühne verdienen."
font_tagline = ImageFont.truetype("/System/Library/Fonts/Supplemental/Arial.ttf", 30)
tl_bbox = draw.textbbox((0, 0), tagline, font=font_tagline)
tl_w = tl_bbox[2] - tl_bbox[0]
draw.text(((W - tl_w) // 2, H // 2 + 90), tagline, font=font_tagline, fill=FAINT)

out_path = os.path.join(ROOT, "public/assets/og-image.png")
canvas.save(out_path)
print(f"saved {out_path}")
