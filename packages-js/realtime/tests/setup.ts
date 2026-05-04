// Setup global de testes para `@arqel-dev/realtime`.
// Cada arquivo de teste gerencia seus próprios spies — não usamos
// `vi.restoreAllMocks()` global porque ele zeraria implementações de
// mocks hoisted via `vi.hoisted` + `vi.mock`.
export {};
