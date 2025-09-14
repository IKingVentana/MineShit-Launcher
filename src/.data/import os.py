import os

libs_dir = r"C:\Users\peren\AppData\Roaming\.mineshit-create\libraries"
jars = []

for root, dirs, files in os.walk(libs_dir):
    for file in files:
        if file.endswith(".jar"):
            jars.append(os.path.join(root, file))

jars.append(r"C:\Users\peren\AppData\Roaming\.mineshit-create\versions\1.20.1-forge-47.4.0\1.20.1-forge-47.4.0.jar")

print(';'.join(jars))
print("Done")