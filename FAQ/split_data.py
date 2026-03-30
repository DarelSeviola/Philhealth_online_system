import json
import random
import argparse

def main():
    parser = argparse.ArgumentParser(description='Split a JSONL file into train/validation sets.')
    parser.add_argument('input_file', nargs='?', default='augmented_faq.jsonl',
                        help='Path to the input JSONL file (default: augmented_faq.jsonl)')
    parser.add_argument('--train', default='train.jsonl',
                        help='Output file for training data (default: train.jsonl)')
    parser.add_argument('--val', default='val.jsonl',
                        help='Output file for validation data (default: val.jsonl)')
    parser.add_argument('--split', type=float, default=0.8,
                        help='Fraction for training (default: 0.8)')
    parser.add_argument('--seed', type=int, default=42,
                        help='Random seed for reproducibility (default: 42)')
    args = parser.parse_args()

    # Set random seed for reproducibility
    random.seed(args.seed)

    # Load all examples
    print(f"Loading data from {args.input_file}...")
    with open(args.input_file, 'r', encoding='utf-8') as f:
        data = [json.loads(line) for line in f]

    print(f"Loaded {len(data)} examples.")

    # Shuffle
    random.shuffle(data)

    # Split
    split_idx = int(args.split * len(data))
    train_data = data[:split_idx]
    val_data = data[split_idx:]

    # Write training set
    with open(args.train, 'w', encoding='utf-8') as f:
        for item in train_data:
            json.dump(item, f, ensure_ascii=False)
            f.write('\n')

    # Write validation set
    with open(args.val, 'w', encoding='utf-8') as f:
        for item in val_data:
            json.dump(item, f, ensure_ascii=False)
            f.write('\n')

    print(f"Saved {len(train_data)} examples to {args.train}")
    print(f"Saved {len(val_data)} examples to {args.val}")

if __name__ == "__main__":
    main()