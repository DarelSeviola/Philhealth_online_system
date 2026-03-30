import json
import re
from docx import Document

# ----------------------------------------------------------------------
# Cleaning functions
# ----------------------------------------------------------------------
def clean_text(text):
    """Normalize whitespace."""
    if not text:
        return ""
    text = re.sub(r'\s+', ' ', text)
    return text.strip()

def remove_leading_number(text):
    """Remove enumeration like '1.' at the beginning."""
    return re.sub(r'^\s*\d+\.\s*', '', text)

def clean_question(text):
    text = clean_text(text)
    text = remove_leading_number(text)
    return text

def clean_answer(text):
    text = text.replace('●', '-').replace('•', '-')
    return clean_text(text)

# ----------------------------------------------------------------------
# Extract Q&A from all tables in the DOCX file
# ----------------------------------------------------------------------
def extract_qa_from_docx(docx_path):
    """Yield (question, answer) pairs from all tables."""
    doc = Document(docx_path)
    for table in doc.tables:
        rows = list(table.rows)
        if not rows:
            continue

        # Skip header row if it contains "TANONG" / "SAGOT"
        start_row = 0
        if rows and len(rows[0].cells) >= 2:
            first_cell = rows[0].cells[0].text.strip().upper()
            second_cell = rows[0].cells[1].text.strip().upper()
            if "TANONG" in first_cell or "SAGOT" in second_cell:
                start_row = 1

        for row in rows[start_row:]:
            cells = row.cells
            if len(cells) < 2:
                continue
            q = clean_question(cells[0].text)
            a = clean_answer(cells[1].text)
            if q and a:
                yield q, a

# ----------------------------------------------------------------------
# Main
# ----------------------------------------------------------------------
def main():
    docx_path = r"C:\xampp\htdocs\philhealth_queue\FAQ\FREQUENTLY ASKED QUESTIONS.docx"
    print("Extracting Q&A from DOCX...")
    qa_pairs = list(extract_qa_from_docx(docx_path))
    print(f"Found {len(qa_pairs)} question-answer pairs.")

    if not qa_pairs:
        print("No Q&A pairs found. Check the file path or table structure.")
        return

    # System prompt for the assistant
    SYSTEM_PROMPT = (
        "You are a helpful PhilHealth assistant. Answer questions accurately "
        "based only on the provided FAQ information. If the answer is not found, "
        "politely say you don't know and suggest contacting PhilHealth directly."
    )

    # Build messages format for each pair
    formatted_data = []
    for q, a in qa_pairs:
        messages = [
            {"role": "system", "content": SYSTEM_PROMPT},
            {"role": "user", "content": q},
            {"role": "assistant", "content": a}
        ]
        formatted_data.append({"messages": messages})

    # Write all examples to a single JSONL file
    output_file = 'philhealth_faq.jsonl'
    with open(output_file, 'w', encoding='utf-8') as f:
        for item in formatted_data:
            json.dump(item, f, ensure_ascii=False)
            f.write('\n')

    print(f"Saved {len(formatted_data)} examples to {output_file}")

if __name__ == "__main__":
    main()