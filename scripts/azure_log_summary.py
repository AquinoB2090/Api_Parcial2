"""Imprime únicamente fallos recientes, ocultando valores secretos de App Settings."""
import json
import re
import sys
import zipfile

settings = json.load(open(sys.argv[2], encoding='utf-8'))
secrets = [str(s['value']) for s in settings if any(word in s['name'].upper() for word in ['PASSWORD', 'SECRET', 'KEY', 'TOKEN', 'CONNECTION']) and len(str(s['value'])) > 3]
with zipfile.ZipFile(sys.argv[1]) as archive:
    names = sorted(n for n in archive.namelist() if n.endswith('.log'))[-20:]
    for name in names:
        lines = archive.read(name).decode('utf-8', errors='replace').splitlines()
        errors = lines[-120:] if 'default_docker.log' in name else []
        for i, line in enumerate(lines):
            if re.search(r'(SQLSTATE|Exception|Fatal|ERROR|error:|failed|could not|not found|migrat|Configuring Laravel|php-fpm)', line, re.I):
                errors.extend(lines[max(0, i-1):i+3])
        output = '\n'.join(dict.fromkeys(errors))[-18000:]
        for secret in secrets:
            output = output.replace(secret, '[REDACTED]')
        output = re.sub(r'(?i)(password|pwd|secret|token)([=:]\s*)[^\s;]+', r'\1\2[REDACTED]', output)
        if output:
            print('DIAGNOSTIC', name)
            print(output)
