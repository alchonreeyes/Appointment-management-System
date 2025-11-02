import sys
import pytesseract
from PIL import Image

def validate_id_image(image_path):
    try:
        img = Image.open(image_path)
        text = pytesseract.image_to_string(img)

        # Check if text contains key phrases indicating an ID
        keywords = ['ID', 'License', 'Passport', 'National ID', 'SSS']
        for keyword in keywords:
            if keyword.lower() in text.lower():
                return 'valid'
        
        return 'invalid'

    except Exception as e:
        return 'invalid'

if __name__ == "__main__":
    image_path = sys.argv[1]
    result = validate_id_image(image_path)
    print(result)
