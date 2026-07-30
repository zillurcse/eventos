declare module '@zoom/meetingsdk/embedded' {
  const ZoomMtgEmbedded: {
    createClient(): any
  }
  export default ZoomMtgEmbedded
}

declare module 'agora-rtc-sdk-ng' {
  const AgoraRTC: {
    setLogLevel(level: number): void
    createClient(options: { mode: string, codec: string }): any
    createMicrophoneAndCameraTracks(): Promise<any[]>
  }
  export default AgoraRTC
}

declare module 'hls.js' {
  export default class Hls {
    static Events: { ERROR: string }
    static isSupported(): boolean
    constructor(options?: Record<string, unknown>)
    loadSource(src: string): void
    attachMedia(media: HTMLMediaElement): void
    on(event: string, handler: (...args: any[]) => void): void
    destroy(): void
  }
}
