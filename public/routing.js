// Only the known www alias is redirected. Other hosts and local development stay unchanged.
export function canonicalUrl(value) {
  const url=new URL(value);
  if (!['http:','https:'].includes(url.protocol) || url.hostname!=='www.vdnt.bmimportexport.com.py') return null;
  url.protocol='https:';
  url.hostname='vdnt.bmimportexport.com.py';
  url.port='';url.username='';url.password='';
  return url.href;
}
