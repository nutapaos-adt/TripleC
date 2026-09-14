// Helper กลางสำหรับเรียก AI (OpenRouter) — ใช้ร่วมกันทั้งผู้ช่วย AI ระดับ 1 และระดับ 2
// รับ prompt ที่สั่งให้ตอบเป็น JSON แล้วคืนค่าเป็น object ที่ parse แล้ว
// โยน error ที่มีข้อความอ่านง่ายเมื่อเรียกไม่สำเร็จ/timeout/parse ไม่ได้ — ผู้เรียกต้อง catch เอง
import { OPENROUTER_API_KEY, AI_MODEL } from "./ai-config.js";

const ENDPOINT = "https://openrouter.ai/api/v1/chat/completions";
const DEFAULT_TIMEOUT_MS = 20000;

function stripCodeFence(text) {
  const match = text.match(/```(?:json)?\s*([\s\S]*?)\s*```/i);
  return (match ? match[1] : text).trim();
}

export async function callAiJson(prompt, { timeoutMs = DEFAULT_TIMEOUT_MS } = {}) {
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), timeoutMs);

  let response;
  try {
    response = await fetch(ENDPOINT, {
      method: "POST",
      headers: {
        Authorization: `Bearer ${OPENROUTER_API_KEY}`,
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        model: AI_MODEL,
        messages: [{ role: "user", content: prompt }],
      }),
      signal: controller.signal,
    });
  } catch (err) {
    if (err.name === "AbortError") throw new Error("เรียก AI ไม่สำเร็จ: หมดเวลารอ (timeout)");
    throw new Error(`เรียก AI ไม่สำเร็จ: ${err.message}`);
  } finally {
    clearTimeout(timeoutId);
  }

  if (!response.ok) {
    throw new Error(`AI ตอบกลับผิดพลาด (HTTP ${response.status})`);
  }

  const data = await response.json();
  const rawText = data.choices?.[0]?.message?.content ?? "";

  try {
    return JSON.parse(stripCodeFence(rawText));
  } catch {
    throw new Error("AI ตอบกลับมาไม่ใช่ JSON ที่อ่านได้ ลองใหม่อีกครั้ง");
  }
}

export { AI_MODEL };
