"""Run against a local Apache fixture serving public/ with AllowOverride All.
Example: python3 tests/apache-redirect.py (fixture listens on 127.0.0.1:8092).
"""
from http.client import HTTPConnection

base='https://vdnt.bmimportexport.com.py'
def request(host,path='/'):
    c=HTTPConnection('127.0.0.1',8092,timeout=5)
    c.request('GET',path,headers={'Host':host})
    r=c.getresponse();result=(r.status,r.getheader('Location'));r.read();c.close()
    return result

for host in ['www.vdnt.bmimportexport.com.py','WWW.VDNT.BMIMPORTEXPORT.COM.PY','www.vdnt.bmimportexport.com.py:443','www.vdnt.bmimportexport.com.py:80']:
    for path in ['/','/index.html?from=bookmark','/folder%20name/?a=1&b=2']:
        assert request(host,path)==(308,base+path),(host,path,request(host,path))
for host in ['vdnt.bmimportexport.com.py','localhost','www.vdnt.bmimportexport.com.py.evil.example']:
    assert request(host)==(200,None),(host,request(host))
print('PASS: real Apache .htaccess redirect, case/default-port handling, encoded path/query preservation and no canonical redirect loop.')
