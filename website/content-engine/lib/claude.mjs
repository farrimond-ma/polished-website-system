import Anthropic from '@anthropic-ai/sdk';

// One place for every model call the engine makes.
// - claude-opus-5 with adaptive thinking: guides are long, detailed and must be accurate.
// - Structured outputs (output_config.format json_schema) so the article always parses.
// - Streaming + finalMessage(): long outputs would otherwise risk HTTP timeouts.
// - The static brief (system prompt) carries cache_control, so the revise pass reuses it.
// - Server-side refusal fallback ("default" routing) so an over-cautious decline does not
//   silently skip a scheduled post.
export const MODEL = process.env.CONTENT_MODEL || 'claude-opus-5';

let client;
const getClient = () => {
  if (!client) client = new Anthropic(); // reads ANTHROPIC_API_KEY from the environment
  return client;
};

export async function generateJSON({ system, user, schema, effort = 'high', maxTokens = 64000, label = 'request' }) {
  // Offline pipeline testing only: CONTENT_ENGINE_MOCK=<file.json> returns that file instead of calling the API.
  if (process.env.CONTENT_ENGINE_MOCK) {
    console.log(`[claude] ${label}: MOCK response from ${process.env.CONTENT_ENGINE_MOCK}`);
    return JSON.parse((await import('fs')).readFileSync(process.env.CONTENT_ENGINE_MOCK, 'utf8'));
  }
  const stream = getClient().beta.messages.stream({
    model: MODEL,
    max_tokens: maxTokens,
    betas: ['server-side-fallback-2026-07-01'],
    fallbacks: 'default',
    thinking: { type: 'adaptive' },
    output_config: { effort, format: { type: 'json_schema', schema } },
    system: [{ type: 'text', text: system, cache_control: { type: 'ephemeral' } }],
    messages: [{ role: 'user', content: user }],
  });
  const message = await stream.finalMessage();

  const u = message.usage || {};
  console.log(`[claude] ${label}: model=${message.model} stop=${message.stop_reason} in=${u.input_tokens} out=${u.output_tokens} cache_read=${u.cache_read_input_tokens || 0}`);

  if (message.stop_reason === 'refusal') {
    throw new Error(`${label}: the model declined (${message.stop_details?.category || 'no category'}): ${message.stop_details?.explanation || ''}`);
  }
  if (message.stop_reason === 'max_tokens') throw new Error(`${label}: output hit max_tokens before finishing.`);

  const text = message.content.filter((b) => b.type === 'text').map((b) => b.text).join('');
  try {
    return JSON.parse(text);
  } catch {
    throw new Error(`${label}: response was not valid JSON (first 300 chars): ${text.slice(0, 300)}`);
  }
}

export { Anthropic };
