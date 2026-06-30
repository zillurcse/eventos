/** Multipart image upload → returns the created File's id + public URL. */
export function useUpload() {
  const api = useApi()

  async function upload(file: File, opts: { collection?: string, path?: string } = {}) {
    const fd = new FormData()
    fd.append('file', file)
    fd.append('collection', opts.collection || 'cover')
    const res = await api<{ data: { id: number, uuid: string, url: string } }>(
      opts.path || '/uploads',
      { method: 'POST', body: fd },
    )
    return res.data
  }

  return { upload }
}
