import openai

response = openai.ChatCompletion.create(
    model="ft:gpt-4.1-mini-2025-04-14:personal::DCd6cV35",  # <-- your model ID
    messages=[
        {"role": "system", "content": "You are a helpful PhilHealth assistant."},
        {"role": "user", "content": "Ano ang Philhealth Yakap?"}
    ]
)

print(response.choices[0].message.content)