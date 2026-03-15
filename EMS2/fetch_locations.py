import json
import urllib.request
import ssl

ssl._create_default_https_context = ssl._create_unverified_context

def fetch_json(url):
    req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
    try:
        with urllib.request.urlopen(req) as response:
            return json.loads(response.read().decode())
    except Exception as e:
        print(f"Error fetching {url}: {e}")
        return []

region_code = "110000000"

davao_region = []

provinces = fetch_json(f"https://psgc.gitlab.io/api/regions/{region_code}/provinces/")
cities = fetch_json(f"https://psgc.gitlab.io/api/regions/{region_code}/cities/")

# Process provinces
for prov in provinces:
    prov_data = {
        "name": prov["name"],
        "cities": []
    }
    cities_muns = fetch_json(f"https://psgc.gitlab.io/api/provinces/{prov['code']}/cities-municipalities/")
    for cm in cities_muns:
        cm_data = {
            "name": cm["name"],
            "barangays": []
        }
        barangays = fetch_json(f"https://psgc.gitlab.io/api/cities-municipalities/{cm['code']}/barangays/")
        for bg in barangays:
            cm_data["barangays"].append(bg["name"])
        prov_data["cities"].append(cm_data)
    davao_region.append(prov_data)

# Process independent cities (like Davao City)
for city in cities:
    # Check if city is already in a province
    found = False
    for p in davao_region:
        for c in p["cities"]:
            if c["name"] == city["name"]:
                found = True
    
    if not found:
        # We can add Davao City as a "province" in our UI dropdown, or under a pseudo-province "Davao Region (Independent Cities)"
        prov_data = {
            "name": city["name"] + " (HUC)",
            "cities": []
        }
        cm_data = {
            "name": city["name"],
            "barangays": []
        }
        barangays = fetch_json(f"https://psgc.gitlab.io/api/cities/{city['code']}/barangays/")
        for bg in barangays:
            cm_data["barangays"].append(bg["name"])
        prov_data["cities"].append(cm_data)
        davao_region.append(prov_data)

with open("c:\\xampp\\htdocs\\dranhs-portal\\EMS2\\davao-address.js", "w") as f:
    f.write("const davaoRegionData = ")
    json.dump(davao_region, f, separators=(',', ':'))
    f.write(";")

print("Done writing davao-address.js")
