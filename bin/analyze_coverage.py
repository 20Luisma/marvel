import xml.etree.ElementTree as ET
import os

def analyze_coverage(file_path):
    try:
        tree = ET.parse(file_path)
        root = tree.getroot()
        
        files_data = []
        
        for file_elem in root.findall('.//file'):
            name = file_elem.get('name')
            metrics = file_elem.find('metrics')
            
            if metrics is not None:
                statements = int(metrics.get('statements', 0))
                covered_statements = int(metrics.get('coveredstatements', 0))
                uncovered = statements - covered_statements
                
                if statements > 0:
                    coverage_percent = (covered_statements / statements) * 100
                else:
                    coverage_percent = 100.0
                
                files_data.append({
                    'name': name,
                    'statements': statements,
                    'covered': covered_statements,
                    'uncovered': uncovered,
                    'percent': coverage_percent
                })
        
        # Sort by uncovered lines (descending)
        files_data.sort(key=lambda x: x['uncovered'], reverse=True)
        
        print(f"{'File':<80} | {'Uncovered':<10} | {'Total':<10} | {'Coverage %':<10}")
        print("-" * 120)
        
        for i, file in enumerate(files_data[:15]): # Top 15 to be safe
            short_name = file['name'].split('clean-marvel/')[-1]
            print(f"{short_name:<80} | {file['uncovered']:<10} | {file['statements']:<10} | {file['percent']:.2f}%")

    except Exception as e:
        print(f"Error parsing XML: {e}")

if __name__ == "__main__":
    analyze_coverage('/Users/admin/Desktop/Proyecto Marvel local y Hosting/clean-marvel/coverage.xml')
