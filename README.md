
```
factscanai_backend
├─ auth
├─ config
│  └─ db.php
├─ login.php
└─ signup.php

```app/src/main/
├── AndroidManifest.xml
├── res/
│   └── xml/
│       └── network_security_config.xml
└── java/com/factscanai/app/
    ├── data/
    │   ├── api/
    │   │   ├── AuthApiService.kt
    │   │   └── RetrofitClient.kt
    │   ├── model/
    │   │   ├── AuthModels.kt
    │   │   └── ErrorResponse.kt
    │   └── repository/
    │       ├── AuthRepository.kt
    │       └── AuthRepositoryImpl.kt
    └── ui/
        ├── auth/
        │   ├── AuthUiState.kt
        │   ├── AuthViewModel.kt
        │   ├── LoginScreen.kt
        │   └── SignupScreen.kt
        └── theme/
            ├── Color.kt
            └── Theme.kt
