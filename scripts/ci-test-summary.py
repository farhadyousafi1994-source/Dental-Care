"""Expose concise JUnit failures in annotations when runner log downloads are unavailable."""
import pathlib
import sys
import xml.etree.ElementTree as ET

path = pathlib.Path(sys.argv[1])
if not path.exists():
    sys.exit(0)
cases = ET.parse(path).findall('.//testcase')
failures = []
for case in cases:
    problem = case.find('failure')
    if problem is None:
        problem = case.find('error')
    if problem is not None:
        lines = [line.strip() for line in (problem.text or problem.get('message', '')).splitlines() if line.strip()]
        failures.append(case.get('classname', '') + '::' + case.get('name', '') + '\n' + '\n'.join(lines[:4])[:700])
skipped = sum(case.find('skipped') is not None for case in cases)
print(f'::notice title=MySQL test summary::{len(cases)} test cases; {len(failures)} failures/errors; {skipped} skipped')
chunks = ['']
for failure in failures:
    if len(chunks[-1]) + len(failure) > 3000:
        chunks.append('')
    chunks[-1] += failure + '\n\n'
for chunk in chunks:
    if chunk:
        escaped = chunk.replace('%', '%25').replace('\n', '%0A').replace('\r', '%0D')
        print('::error title=MySQL test failures::' + escaped)
