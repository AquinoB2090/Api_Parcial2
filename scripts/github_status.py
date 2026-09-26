"""Consulta GitHub usando el administrador de credenciales existente, sin imprimir secretos."""
import json, os, subprocess, sys, urllib.request, urllib.error
env = dict(os.environ, GIT_TERMINAL_PROMPT="0", GCM_INTERACTIVE="never")
result = subprocess.run(["git", "credential", "fill"], input="protocol=https\nhost=github.com\npath=AquinoB2090/Api_Parcial2.git\n\n", text=True, capture_output=True, env=env)
credentials = dict(line.split("=", 1) for line in result.stdout.splitlines() if "=" in line)
headers = {"Accept": "application/vnd.github+json", "User-Agent": "ApiParcial-verification", "X-GitHub-Api-Version": "2022-11-28"}
if credentials.get("password"):
    headers["Authorization"] = "Bearer " + credentials["password"]
path = sys.argv[1] if len(sys.argv) > 1 else "/actions/runs?per_page=3"
assert path.startswith("/") and not path.startswith("//")
url = "https://api.github.com/repos/AquinoB2090/Api_Parcial2" + path
try:
    with urllib.request.urlopen(urllib.request.Request(url, headers=headers), timeout=30) as response:
        payload = json.load(response)
    if "workflow_runs" in payload:
        print(json.dumps([{"id": x["id"], "status": x["status"], "conclusion": x["conclusion"], "sha": x["head_sha"], "url": x["html_url"]} for x in payload["workflow_runs"]], indent=2))
    elif "jobs" in payload:
        print(json.dumps([{"name": x["name"], "status": x["status"], "conclusion": x["conclusion"], "steps": [{"name": s["name"], "status": s["status"], "conclusion": s["conclusion"]} for s in x["steps"]]} for x in payload["jobs"]], indent=2))
    elif isinstance(payload, list):
        print(json.dumps([{"id": x.get("id"), "state": x.get("state"), "environment_url": x.get("environment_url"), "url": x.get("url")} for x in payload], indent=2))
    else:
        print(json.dumps({k: payload.get(k) for k in ["id","status","conclusion","html_url","default_branch","visibility"]}, indent=2))
except urllib.error.HTTPError as error:
    print("GitHub HTTP", error.code)
    sys.exit(1)
