# AUTOR: JOSEF HAVRANEK AND TOMAS JURICEK
import requests
import json
from secret import password,login  #contain password

headers = {'Content-Type': 'application/json'}
path = '.../public/'


def vratLimity():
    data = json.dumps({"login":login(), "password":password()})
    pom2 = []
    pom2.append(1)
    pom2.append(1)
    r= None
    try:
        r = requests.post(path + "heartbeat", headers = headers, data = data,timeout=2)
    except:
        print("Error: can not join onto server For heartbeat")
        return pom2

    pom = None
    try:
        pom = r.json()
    except:
        print("Error: canot parse JSON in vratLimity json is", r, "parsed into ", pom)
        return pom2
    if r.status_code < 200 or r.status_code > 299:
        print("Error:", r.status_code,pom)
    else:
        for x in pom["garaze"]:
            inner = {"limit_max":x["limit_max"], "limit_min":x["limit_min"]}
            pom2[x["id"]-1] = inner
    return pom2

def vratStavy():
    r = None
    pom2 = []
    pom2.append(1)
    pom2.append(1)
    try:
        r = requests.get(path + "garaze?login=malinka&password=" + password(), headers = headers, timeout = 2)
    except:
        print("Error: can not join onto server For vratStavy")
        return pom2
    pom=None
    try:
        pom = r.json()
    except:
        print("Error: canot parse  JSON in vrat stavy JSON IS",r)
        return pom2
    if r.status_code < 200 or r.status_code > 299:
        print("Error:", r.status_code, pom["msg"])
    else:

        for x in pom["garaze"]:
            pom2[x["id"]-1] = x["id_stav"]
        return pom2

def updateStav(stav, idecko):
    try:
        data = json.dumps({"garaze":[{"stav":stav, "id":idecko}]})
        r = requests.post(path + "garaze/update?login=malinka&password=" + password(), headers = headers, data = data,timeout=2)
        return True
    except:
        return False
