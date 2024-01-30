import uuid
import hashlib

def data_to_uuid(*data: tuple[str]) -> uuid.UUID:


    s = hashlib.md5(bytes(".".join(list(data)), 'utf-8'))

    print(s.hexdigest())

    return uuid.UUID(bytes=s.digest())

id = data_to_uuid("lexique/", "تَ")

print()