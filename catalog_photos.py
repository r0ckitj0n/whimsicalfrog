import osxphotos
import json

# Setup
ALBUM_NAME = "Big Family"
catalog_file = "photo_timeline.md"

print(f"Opening Apple Photos database...")
photosdb = osxphotos.PhotosDB()

print(f"Searching for album: '{ALBUM_NAME}'...")
photos = photosdb.photos(albums=[ALBUM_NAME])

if not photos:
    print(f"Error: Could not find an album named '{ALBUM_NAME}'. Check the name in Apple Photos.")
else:
    with open(catalog_file, "w") as f:
        f.write(f"# {ALBUM_NAME} Analysis Catalog\n\n")
        f.write("| Filename | UUID | Current Date | People | AI Proposed Date | Keep Original? |\n")
        f.write("| :--- | :--- | :--- | :--- | :--- | :--- |\n")

        for p in photos:
            people = ", ".join(p.persons) if p.persons else "Unknown"
            # We use p.date.strftime for a cleaner look in the MD file
            f.write(f"| {p.filename} | {p.uuid} | {p.date} | {people} | [PENDING] | [CHECK] |\n")

    print(f"Success! Cataloged {len(photos)} photos into '{catalog_file}'.")
