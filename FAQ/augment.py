import json
import random
from transformers import AutoTokenizer, AutoModelForSeq2SeqLM

# ----------------------------------------------------------------------
# Load T5 model directly
# ----------------------------------------------------------------------
model_name = "google/flan-t5-small"
tokenizer = AutoTokenizer.from_pretrained(model_name)
model = AutoModelForSeq2SeqLM.from_pretrained(model_name)

def paraphrase(question, num_return=3):
    """Generate paraphrased versions using direct model call."""
    prompt = f"paraphrase: {question}"
    inputs = tokenizer(prompt, return_tensors="pt", truncation=True, max_length=64)
    outputs = model.generate(
        **inputs,
        max_length=64,
        num_return_sequences=num_return,
        num_beams=5,
        temperature=1.0,
        do_sample=True
    )
    return [tokenizer.decode(out, skip_special_tokens=True).strip() for out in outputs]

# ----------------------------------------------------------------------
# Load original data
# ----------------------------------------------------------------------
original_file = "FAQ/philhealth_faq.jsonl"   # relative path
augmented_file = "augmented_faq.jsonl"

with open(original_file, 'r', encoding='utf-8') as f:
    original_data = [json.loads(line) for line in f]

print(f"Loaded {len(original_data)} original examples.")

# ----------------------------------------------------------------------
# Augment: for each QA pair, create paraphrased questions
# ----------------------------------------------------------------------
augmented_data = []
for item in original_data:
    augmented_data.append(item)  # keep original

    q = item["messages"][1]["content"]
    a = item["messages"][2]["content"]

    try:
        paraphrases = paraphrase(q, num_return=2)  # create 2 variants
        for pq in paraphrases:
            new_messages = [
                {"role": "system", "content": item["messages"][0]["content"]},
                {"role": "user", "content": pq},
                {"role": "assistant", "content": a}
            ]
            augmented_data.append({"messages": new_messages})
    except Exception as e:
        print(f"Paraphrase failed for: {q}\nError: {e}")

print(f"Augmented dataset now has {len(augmented_data)} examples.")
random.shuffle(augmented_data)

with open(augmented_file, 'w', encoding='utf-8') as f:
    for item in augmented_data:
        json.dump(item, f, ensure_ascii=False)
        f.write('\n')

print(f"Saved augmented data to {augmented_file}")