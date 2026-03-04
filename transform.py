#!/usr/bin/env python3
import re

with open('/Users/brian/Developer/cline/vend/tests/Unit/Jobs/CallFunctionTest.php', 'r') as f:
    content = f.read()

# Function to transform each match
def transform_request(match):
    indent = match.group(1)
    id_val = match.group(2)
    method = match.group(3)
    params = match.group(4)

    # Build the new format
    result = f"{indent}$requestObject = RequestObjectData::from([\n"
    result += f"{indent}    'protocol' => VendProtocol::VERSION,\n"
    result += f"{indent}    'id' => {id_val},\n"
    result += f"{indent}    'call' => CallData::from([\n"
    result += f"{indent}        'function' => {method},\n"
    result += f"{indent}        'arguments' => {params},\n"
    result += f"{indent}    ]),\n"
    result += f"{indent}]);"

    return result

# Pattern to match RequestObjectData::from with jsonrpc
pattern = r'(\s+)\$requestObject = RequestObjectData::from\(\[\s*[\'"]jsonrpc[\'"]\s*=>\s*[\'"]2\.0[\'"]\s*,\s*[\'"]id[\'"]\s*=>\s*([^,]+),\s*[\'"]method[\'"]\s*=>\s*([^,]+),\s*[\'"]params[\'"]\s*=>\s*(.+?),?\s*\]\);'

content = re.sub(pattern, transform_request, content, flags=re.MULTILINE | re.DOTALL)

with open('/Users/brian/Developer/cline/vend/tests/Unit/Jobs/CallFunctionTest.php', 'w') as f:
    f.write(content)

print("Transformation complete!")
