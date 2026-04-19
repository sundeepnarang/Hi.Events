import sys
import csv
import argparse
import json
import argparse

def parse_po(file_path):
    entries = []
    current_entry = {'refs': [], 'msgid': '', 'msgstr': '', 'headers': [], 'msgctxt': ''}
    state = None
    
    with open(file_path, 'r', encoding='utf-8') as f:
        for line in f:
            line = line.strip()
            if not line:
                if current_entry['msgid'] or current_entry['msgstr'] or current_entry['headers'] or current_entry['msgctxt']:
                    entries.append(current_entry)
                    current_entry = {'refs': [], 'msgid': '', 'msgstr': '', 'headers': [], 'msgctxt': ''}
                state = None
                continue
                
            if line.startswith('#:'):
                current_entry['refs'].append(line[2:].strip())
                continue
            elif line.startswith('#'):
                current_entry['headers'].append(line)
                continue
                
            if line.startswith('msgctxt '):
                state = 'msgctxt'
                current_entry[state] += extract_string(line[8:].strip())
            elif line.startswith('msgid '):
                state = 'msgid'
                current_entry[state] += extract_string(line[6:].strip())
            elif line.startswith('msgstr '):
                state = 'msgstr'
                current_entry[state] += extract_string(line[7:].strip())
            elif line.startswith('"') and line.endswith('"'):
                if state:
                    current_entry[state] += extract_string(line)
                    
    if current_entry['msgid'] or current_entry['msgstr'] or current_entry['headers'] or current_entry['msgctxt']:
        entries.append(current_entry)
        
    return entries

def extract_string(s):
    if s.startswith('"') and s.endswith('"'):
        return s[1:-1]
    return s

def escape_string(s):
    if not s:
        return '""'
    if '\\n' in s:
        parts = s.split('\\n')
        lines = ['""']
        for i, part in enumerate(parts):
            if i < len(parts) - 1:
                lines.append(f'"{part}\\n"')
            elif part:
                lines.append(f'"{part}"')
        return '\n'.join(lines)
    else:
        return f'"{s}"'

def to_csv(po_path, csv_path, target_lang=None):
    entries = parse_po(po_path)
    with open(csv_path, 'w', encoding='utf-8', newline='') as f:
        writer = csv.writer(f)
        writer.writerow(['source_references', 'msgctxt', 'msgid', 'msgstr', 'comments'])
        
        row_num = 2
        for entry in entries:
            refs = ' | '.join(entry['refs'])
            headers = '\n'.join(entry['headers'])
            
            # Use Google Translate formula if target_lang is provided and the string is untranslated (except for PO header)
            msgstr = entry['msgstr']
            if target_lang and entry['msgid'] and not msgstr:
                msgstr = f'=GOOGLETRANSLATE(C{row_num}, "en", "{target_lang}")'
                
            writer.writerow([refs, entry['msgctxt'], entry['msgid'], msgstr, headers])
            row_num += 1
            
    print(f"Successfully converted {po_path} to {csv_path}")

def from_csv(csv_path, po_path):
    entries = []
    with open(csv_path, 'r', encoding='utf-8') as f:
        reader = csv.DictReader(f)
        for row in reader:
            refs = [r.strip() for r in row['source_references'].split('|') if r.strip()]
            headers = row['comments'].split('\n') if row['comments'] else []
            entries.append({
                'refs': refs,
                'msgctxt': row['msgctxt'],
                'msgid': row['msgid'],
                'msgstr': row['msgstr'],
                'headers': headers
            })
            
    with open(po_path, 'w', encoding='utf-8') as f:
        for entry in entries:
            for header in entry['headers']:
                if header:
                    f.write(f"{header}\n")
            for ref in entry['refs']:
                f.write(f"#: {ref}\n")
                
            if entry['msgctxt']:
                f.write(f"msgctxt {escape_string(entry['msgctxt'])}\n")
                
            f.write(f"msgid {escape_string(entry['msgid'])}\n")
            f.write(f"msgstr {escape_string(entry['msgstr'])}\n\n")
            
    print(f"Successfully converted {csv_path} to {po_path}")

def json_to_csv(json_path, csv_path, target_lang=None):
    with open(json_path, 'r', encoding='utf-8') as f:
        data = json.load(f)
        
    with open(csv_path, 'w', encoding='utf-8', newline='') as f:
        writer = csv.writer(f)
        writer.writerow(['source_references', 'msgctxt', 'msgid', 'msgstr', 'comments'])
        
        row_num = 2
        for key, value in data.items():
            msgstr = value
            if target_lang and key and not msgstr:
                msgstr = f'=GOOGLETRANSLATE(C{row_num}, "en", "{target_lang}")'
            writer.writerow(['', '', key, msgstr, ''])
            row_num += 1
            
    print(f"Successfully converted {json_path} to {csv_path}")

def csv_to_json(csv_path, json_path):
    data = {}
    with open(csv_path, 'r', encoding='utf-8') as f:
        reader = csv.DictReader(f)
        for row in reader:
            data[row['msgid']] = row['msgstr']
            
    with open(json_path, 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=4)
        
    print(f"Successfully converted {csv_path} to {json_path}")

def main():
    parser = argparse.ArgumentParser(description="Convert between PO/JSON and CSV")
    parser.add_argument('mode', choices=['to-csv', 'to-po', 'json-to-csv', 'csv-to-json'], help='Conversion mode')
    parser.add_argument('input_file', help='Input file path')
    parser.add_argument('output_file', help='Output file path')
    parser.add_argument('--lang', help='Target language code (e.g. "el"). Insterts =GOOGLETRANSLATE formula in CSV for missing translations.', default=None)
    
    args = parser.parse_args()
    
    if args.mode == 'to-csv':
        to_csv(args.input_file, args.output_file, args.lang)
    elif args.mode == 'to-po':
        from_csv(args.input_file, args.output_file)
    elif args.mode == 'json-to-csv':
        json_to_csv(args.input_file, args.output_file, args.lang)
    elif args.mode == 'csv-to-json ':
        csv_to_json(args.input_file, args.output_file)

if __name__ == '__main__':
    main()
