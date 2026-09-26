"""Validaciones de contratos y workflow sin acceder a credenciales ni a la base."""
import json
import pathlib
import re
import subprocess

root = pathlib.Path(__file__).resolve().parents[1]
spec = json.loads((root / "public/openapi.json").read_text(encoding="utf-8"))
collection = json.loads((root / "docs/Subastas.postman_collection.json").read_text(encoding="utf-8"))
assert spec["openapi"] == "3.0.3"
assert len(collection["item"]) >= 26
def inspect(value):
    if isinstance(value, dict):
        if "$ref" in value:
            assert value["$ref"].startswith("#/components/schemas/")
            assert value["$ref"].split("/")[-1] in spec["components"]["schemas"]
        for item in value.values():
            inspect(item)
    elif isinstance(value, list):
        for item in value:
            inspect(item)
inspect(spec)
routes = json.loads(subprocess.check_output(["php", "artisan", "route:list", "--json", "--path=api"], cwd=root, text=True))
normalize = lambda path: re.sub(r"\{[^}]+\}", "{}", "/" + path.lstrip("/"))
actual = {(method, normalize(route["uri"])) for route in routes for method in route["method"].split("|")}
for path, methods in spec["paths"].items():
    for method in methods:
        assert (method.upper(), normalize(path)) in actual, (method, path)
print(f"OpenAPI: {sum(len(m) for m in spec['paths'].values())} operaciones con rutas reales. Postman: {len(collection['item'])} solicitudes.")
try:
    import yaml
    workflow = yaml.safe_load((root / ".github/workflows/main_apiparcial.yml").read_text(encoding="utf-8"))
    assert {"build", "deploy"} <= workflow["jobs"].keys()
    print("Workflow YAML válido.")
except ImportError:
    print("PyYAML no disponible; validación YAML omitida.")
