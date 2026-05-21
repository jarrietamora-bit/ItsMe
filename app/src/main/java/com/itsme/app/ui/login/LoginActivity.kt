package com.itsme.app.ui.login
import android.content.Intent
import android.os.Bundle
import android.widget.Toast
import androidx.activity.viewModels
import androidx.appcompat.app.AppCompatActivity
import com.itsme.app.databinding.ActivityLoginBinding
import com.itsme.app.ui.main.MainActivity
import com.itsme.app.utils.SessionManager
import com.itsme.app.viewmodel.LoginViewModel

class LoginActivity : AppCompatActivity() {
    private lateinit var b: ActivityLoginBinding
    private val vm: LoginViewModel by viewModels()

    override fun onCreate(s: Bundle?) {
        super.onCreate(s)
        if (SessionManager.isLoggedIn(this)) { go(); return }
        b = ActivityLoginBinding.inflate(layoutInflater); setContentView(b.root)
        b.btnLogin.setOnClickListener {
            val u = b.etUsername.text.toString().trim()
            val p = b.etPassword.text.toString().trim()
            if (u.isEmpty() || p.isEmpty()) { Toast.makeText(this, "Complete todos los campos", Toast.LENGTH_SHORT).show(); return@setOnClickListener }
            vm.login(u, p)
        }
        vm.loginResult.observe(this) { user ->
            if (user != null) { SessionManager.saveUser(this, user.id, user.username, user.isAdmin); go() }
            else Toast.makeText(this, "Usuario o contraseña incorrectos", Toast.LENGTH_SHORT).show()
        }
    }
    private fun go() { startActivity(Intent(this, MainActivity::class.java)); finish() }
}
